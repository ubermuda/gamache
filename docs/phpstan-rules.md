# PHPStan rules

Including `vendor/ubermuda/gamache/extension.neon` in your `phpstan.neon` registers all 28 rules (see the [README](../README.md#phpstan-rules) for setup and parameters).

Every error carries an identifier, so you can opt out of a single rule with PHPStan's `ignoreErrors`:

```neon
parameters:
    ignoreErrors:
        - identifier: enum.notKebabCase
```

All rules live in the `Gamache\PHPStan` namespace.

**Controllers:** [ControllerParentRule](#controllerparentrule) · [ControllerSingleActionRule](#controllersingleactionrule) · [ControllerRouteAttributeRule](#controllerrouteattributerule) · [ControllerTemplateNameRule](#controllertemplatenamerule) · [DenyAccessUnlessGrantedRule](#denyaccessunlessgrantedrule) · [IsGrantedNoFullyAuthRule](#isgrantednofullyauthrule) · [IsGrantedClassLevelRule](#isgrantedclasslevelrule) · [IsGrantedVoterConstantRule](#isgrantedvoterconstantrule) · [CsrfTokenAttributeRule](#csrftokenattributerule)
**Routing:** [RouteNoUnderscorePrefixRule](#routenounderscoreprefixrule) · [RouteParamCamelCaseRule](#routeparamcamelcaserule)
**API:** [ApiRouteConsistencyRule](#apirouteconsistencyrule) · [ApiControllerInputBindingRule](#apicontrollerinputbindingrule)
**CQRS:** [CommandShapeRule](#commandshaperule) · [HandlerShapeRule](#handlershaperule)
**Messenger:** [MessengerHandlerNamespaceRule](#messengerhandlernamespacerule)
**Templates:** [ModuleTemplateNamespaceRule](#moduletemplatenamespacerule)
**Forms & DTOs:** [BuildFormConstraintsRule](#buildformconstraintsrule) · [FormDataClassNotEntityRule](#formdataclassnotentityrule) · [DtoRequestSuffixRule](#dtorequestsuffixrule) · [NotBlankNullableRule](#notblanknullablerule)
**Entities & migrations:** [EntityAsymmetricVisibilityRule](#entityasymmetricvisibilityrule) · [MigrationDescriptionRule](#migrationdescriptionrule) · [RepositoryParameterNameRule](#repositoryparameternamerule)
**Security:** [VoterNotReadonlyRule](#voternotreadonlyrule)
**Translations:** [TranslationCallSiteRule](#translationcallsiterule) · [TranslationAttributeRule](#translationattributerule)
**Misc:** [EnumKebabCaseRule](#enumkebabcaserule) · [PassThroughHelperRule](#passthroughhelperrule)

---

## ControllerParentRule

**Identifier:** `controller.missingAppControllerParent`
**Configured by:** `gamache.controllerBaseClass`

Every class named `*Controller` must extend the configured base controller. The base class itself is exempt.

> `Controller <Class> must extend <BaseClass>.`

```php
// BAD — extends AbstractController directly
class ProfileController extends AbstractController {}

// GOOD
class ProfileController extends AppController {}
```

---

## ControllerSingleActionRule

**Identifier:** `controller.notSingleAction`
**Configured by:** `gamache.controllerBaseClass`

Controllers must be single-action: exactly one public method, `__invoke()`. A public constructor doesn't count.

> `Controller <Class> must have exactly one public method: __invoke().`

```php
// BAD — extra public method
class ProjectController extends AppController
{
    public function __invoke(): Response { /* … */ }
    public function helper(): string { /* … */ }
}

// GOOD
class ProjectController extends AppController
{
    public function __invoke(): Response { /* … */ }
}
```

---

## ControllerRouteAttributeRule

**Identifier:** `controller.missingRouteAttribute`
**Configured by:** `gamache.controllerBaseClass`

Every controller (subclass of the configured base) must carry a `#[Route]` attribute.

> `Controller <Class> must have a #[Route] attribute.`

```php
// BAD — no route
class OrphanController extends AppController { /* … */ }

// GOOD
#[Route('/projects', name: 'project_list')]
class ListProjectsController extends AppController { /* … */ }
```

---

## DenyAccessUnlessGrantedRule

**Identifier:** `controller.denyAccessUnlessGranted`
**Configured by:** `gamache.controllerBaseClass`

Controllers must declare access control with `#[IsGranted]`, not enforce it imperatively inside `__invoke()`. The rule flags, anywhere in the `__invoke()` body:

- `$this->denyAccessUnlessGranted($attribute, $subject)` — but only when the call passes a **subject** (second argument) and `__invoke()` receives a route-resolved parameter (an entity or path argument — anything other than `Request`)
- `$this->createAccessDeniedException(...)` calls
- `new AccessDeniedHttpException(...)` (`Symfony\Component\HttpKernel\Exception`)
- `new AccessDeniedException(...)` (`Symfony\Component\Security\Core\Exception`)

> `AppController::__invoke() must not <call …/instantiate …>. Use #[IsGranted] with a Voter constant and subject.`

An imperative deny over a route-resolved subject almost always means the right **(subject, permission)** pair has not been found yet. The subject can be the most-specific entity the route already resolves — the Voter is free to walk from it to whatever the policy checks (e.g. `Comment` → `comment.version.document.owner`), so "the owning entity isn't a route parameter" is not a reason to go imperative.

**No escape hatches.** A class docblock or comment does **not** exempt a controller, and there is no sanctioned suppression. `createAccessDeniedException()` and `new AccessDenied*Exception()` are always flagged. The only calls the rule leaves alone are the ones where there is genuinely no subject to declare: a role-only check with no subject argument (`denyAccessUnlessGranted('ROLE_ADMIN')`), or a subject that is only resolvable at runtime because `__invoke()` has no route-resolved parameter.

```php
// BAD — subject resolved from the route, denied imperatively
class DeleteProjectController extends AppController
{
    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);
        // …
    }
}

// GOOD — declarative, subject resolved from the route
#[IsGranted(ProjectVoter::DELETE, subject: 'project')]
class DeleteProjectController extends AppController
{
    public function __invoke(Project $project): Response { /* … */ }
}
```

---

## IsGrantedNoFullyAuthRule

**Identifier:** `isGranted.isAuthenticatedFully`

Prohibits `#[IsGranted('IS_AUTHENTICATED_FULLY')]` — it grants access to any authenticated user and bypasses ownership checks. Use a Voter constant with a subject.

> `#[IsGranted('IS_AUTHENTICATED_FULLY')] bypasses Voter-based ownership checks. Specify a Voter constant and a subject instead.`

```php
// BAD
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EditProjectController extends AppController { /* … */ }

// GOOD
#[IsGranted(ProjectVoter::EDIT, subject: 'project')]
class EditProjectController extends AppController { /* … */ }
```

---

## IsGrantedClassLevelRule

**Identifier:** `controller.isGrantedNotClassLevel`
**Configured by:** `gamache.controllerBaseClass`

`#[IsGranted]` on a controller must be declared at the **class** level, not on the action method — single-action controllers carry their access control on the class, like `#[Route]`. Symfony reads `#[IsGranted]` from both the class and the method and resolves the subject from the controller arguments either way, so moving it up is behaviour-preserving.

> `#[IsGranted] on <Class>::<method>() must be declared at the class level, not on the method (single-action controllers carry access control on the class, like #[Route]). The subject still resolves from the controller arguments.`

```php
// BAD — method-level
class DeleteEventController extends AppController
{
    #[IsGranted('delete', 'event')]
    public function __invoke(Event $event): Response { /* … */ }
}

// GOOD — class-level
#[IsGranted('delete', 'event')]
class DeleteEventController extends AppController
{
    public function __invoke(Event $event): Response { /* … */ }
}
```

---

## IsGrantedVoterConstantRule

**Identifier:** `security.isGrantedVoterConstant`
**Configured by:** `gamache.isGrantedAllowedAttributePrefixes`

The attribute argument of `#[IsGranted]` must reference a Voter class constant, not a bare string literal — so each attribute name has a single source of truth and a typo becomes an "undefined constant" error instead of a silent always-deny. Framework attributes that have no Voter constant are exempt: any literal whose value starts with one of the configured prefixes (default `ROLE_`, `IS_AUTHENTICATED`, `PUBLIC_ACCESS`, `IS_IMPERSONATOR`) passes. Both the first positional argument and the named `attribute:` form are checked; non-literal arguments (constant fetches, expressions) are never flagged.

> `The #[IsGranted] attribute '<value>' must reference a Voter class constant (e.g. EventVoter::EDIT), not a string literal. Framework attributes (<prefixes>) are exempt.`

```neon
parameters:
    gamache:
        isGrantedAllowedAttributePrefixes:
            - 'ROLE_'
            - 'IS_AUTHENTICATED'
            - 'PUBLIC_ACCESS'
            - 'IS_IMPERSONATOR'
```

```php
// BAD — string literal
#[IsGranted('edit', subject: 'project')]
class EditProjectController extends AppController { /* … */ }

// GOOD — Voter constant
#[IsGranted(ProjectVoter::EDIT, subject: 'project')]
class EditProjectController extends AppController { /* … */ }

// GOOD — framework attribute, exempt (no Voter constant exists for it)
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AppController { /* … */ }
```

---

## CsrfTokenAttributeRule

**Identifier:** `controller.csrfTokenAttribute`
**Configured by:** `gamache.controllerBaseClass`, `gamache.csrfTokenAttributeClass`

Controllers must validate CSRF tokens declaratively — with a `#[CsrfToken]` attribute checked by a listener before the action runs — not imperatively inside the action body. Within any subclass of the configured base controller, the rule flags two imperative patterns:

- `$this->isCsrfTokenValid(...)` — the `AbstractController` helper
- `$manager->isTokenValid(...)` — where the receiver is a `Symfony\Component\Security\Csrf\CsrfTokenManagerInterface`

Classes that aren't controllers (e.g. the listener that backs the attribute) are not flagged, so the rule never trips on the validation machinery itself. `gamache.csrfTokenAttributeClass` is optional and affects only the message: set it to your project's attribute FQCN (e.g. `App\Security\Attribute\CsrfToken`) and the error names it; leave it empty (the default) for a generic message. The attribute and its listener are project-supplied — gamache enforces the convention, it does not ship the attribute.

> `Controller must not call <method>() to validate CSRF tokens imperatively. Use the #[App\Security\Attribute\CsrfToken] attribute instead; validation runs in the listener before the action.`

```neon
parameters:
    gamache:
        csrfTokenAttributeClass: 'App\Security\Attribute\CsrfToken'
```

```php
// BAD — imperative check inside the action
class DeleteProjectController extends AppController
{
    public function __invoke(Request $request, Project $project): Response
    {
        if (!$this->isCsrfTokenValid('delete_project', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }
        // …
    }
}

// GOOD — declarative; the listener validates before __invoke() runs
#[CsrfToken('delete_project')]
class DeleteProjectController extends AppController
{
    public function __invoke(Project $project): Response { /* … */ }
}
```

---

## ControllerTemplateNameRule

**Identifier:** `controller.templateName`
**Configured by:** `gamache.controllerTemplates` (off by default)

A controller that renders a page template must have a template whose name matches the class name somewhere under its module's template directory: `CreateProjectController` → `create_project.html.twig`.

- `namespacePattern` — regex over controller FQCNs; capture group 1 is the module-relative namespace path (e.g. `Project` from `App\Module\Project\Controller\CreateProjectController`), mirrored under the template root. Controllers that don't match are exempt. Empty (the default) disables the rule.
- `templateDirectory` — template root the captured module path is appended to, relative to the working directory (or absolute). Empty (the default) disables the rule.
- `renderMethods` — method names whose first argument renders a page template (default `[render]`; add project helpers like `renderFormResponse`).

Controllers that render nothing (redirects, JSON, dispatch-only) are skipped, as are controllers that only render partials (`_*.html.twig`). Matching is lenient: case, underscores, and directory separators are ignored, and the filename alone may carry the name — `registration/check_email.html.twig` satisfies `RegistrationCheckEmailController`, `security/login.html.twig` satisfies `LoginController`, and `attach_github_repo.html.twig` satisfies `AttachGitHubRepoController`. Partials are never counted as a controller's template.

> `Controller <Class> renders a template but none under <dir> matches its name (expected "<snake_case>.html.twig"; matching ignores case, underscores, and directories).`

```neon
parameters:
    gamache:
        controllerTemplates:
            namespacePattern: '#^App\\Module\\(.+)\\Controller\\[^\\]+Controller$#'
            templateDirectory: 'templates/Module'
            renderMethods: [render, renderFormResponse]
```

```php
// BAD — IssueBrainstormController renders issue_detail.html.twig and no
// issue_brainstorm.html.twig exists under templates/Module/Project/
final class IssueBrainstormController extends AppController
{
    public function __invoke(): Response
    {
        return $this->render('@Project/issue_detail.html.twig');
    }
}

// GOOD — create_project.html.twig exists under templates/Module/Project/
final class CreateProjectController extends AppController
{
    public function __invoke(): Response
    {
        return $this->render('@Project/create_project.html.twig');
    }
}
```

---

## ModuleTemplateNamespaceRule

**Identifier:** `template.moduleNamespace`
**Configured by:** `gamache.templateNamespaces` (off by default)

Template paths under the module template root must be referenced through their Twig namespace: `@Event/show.html.twig`, not `Module/Event/show.html.twig`. The rule flags string literals matching `<forbiddenPathPrefix><PascalCase>/...` passed as the first argument of a render method.

- `forbiddenPathPrefix` — template-path prefix that must go through a namespace (e.g. `Module/`). Empty (the default) disables the rule.
- `renderMethods` — method names whose first argument is a template path (default `[render, renderView, htmlTemplate, textTemplate]`, covering controllers and `TemplatedEmail` builders).

Dynamically-assembled paths cannot be checked statically and remain convention-only. The Twig-side counterpart for `{% extends %}` / `{% include %}` / import tags is the Twig-CS-Fixer rule of the same name (see [docs/twig-cs-fixer.md](twig-cs-fixer.md#moduletemplatenamespacerule)).

> `Template "Module/Event/show.html.twig" must be referenced through its Twig namespace: "@Event/show.html.twig".`

```neon
parameters:
    gamache:
        templateNamespaces:
            forbiddenPathPrefix: 'Module/'
```

```php
// BAD
return $this->render('Module/Event/show.html.twig');

// GOOD
return $this->render('@Event/show.html.twig');
```

---

## RouteNoUnderscorePrefixRule

**Identifier:** `route.underscorePrefix`

Route paths must not begin with `/_` — Symfony reserves that prefix for internals (profiler, debug toolbar).

> `Route path "<path>" must not begin with "/_". That prefix is reserved for Symfony internals.`

```php
// BAD
#[Route('/_admin/users')]

// GOOD
#[Route('/admin/users')]
```

---

## RouteParamCamelCaseRule

**Identifier:** `route.paramNotCamelCase`

Route parameters must be camelCase, so they mirror the camelCase entity property / DTO field they resolve to (Symfony's `EntityValueResolver` uses the route-param name as the Doctrine lookup field). Requirement suffixes (`{id<\d+>}`) and mapped-param suffixes (`{id:org}`) are stripped before the name is validated.

> `Route parameter "<name>" must be camelCase.`

```php
// BAD
#[Route('/{org_id}/projects/{project_slug}')]

// GOOD
#[Route('/{orgId}/projects/{projectSlug}')]
```

---

## ApiRouteConsistencyRule

**Identifier:** `route.apiConsistency`

A JSON API surface keeps three signals in lock-step: a route **path** under `/api/`, a route **name** prefixed `api_`, and a class in a `\Controller\Api\` namespace. The path is canonical; the name and namespace are derived from it. Any disagreement — a misplaced controller, or a web route accidentally named `api_` — is reported. The name is compared only when the `#[Route]` sets one.

> `API routing convention mismatch on <Class>: path "<path>", name <name>, and namespace <ns> must agree. An "/api/" path requires an "api_" route name and a "\Controller\Api\" namespace (and vice versa).`

```php
// BAD — /api/ path, but the class is not in a Controller\Api namespace
namespace App\Module\Foo\Controller;
#[Route('/api/foo', name: 'api_foo')]
class MisplacedApiController { /* … */ }

// GOOD
namespace App\Module\Foo\Controller\Api;
#[Route('/api/foo', name: 'api_foo')]
class CreateFooController { /* … */ }
```

---

## ApiControllerInputBindingRule

**Identifier:** `controller.apiInputBinding`

A controller in a `\Controller\Api\` namespace must bind input through `#[MapRequestPayload]` and a validated DTO — never a Symfony form (`$this->createForm()`, a `FormInterface`/`FormFactoryInterface` dependency) nor a hand-rolled read of the raw request body (`->getContent()`). Only the forbidden constructs are flagged; a payload parameter is *not* required, so read endpoints (GET with only route params) don't false-positive.

> `API controller <Class> must bind input via #[MapRequestPayload], not a Symfony form ($this->createForm()).`

```php
// BAD
namespace App\Module\Foo\Controller\Api;
class BadController
{
    public function __invoke(Request $request): JsonResponse
    {
        $form = $this->createForm(FooType::class);   // flagged
        $data = json_decode($request->getContent()); // flagged (->getContent())
        // …
    }
}

// GOOD
class GoodController
{
    public function __invoke(#[MapRequestPayload] FooRequest $payload): JsonResponse { /* … */ }
}
```

---

## CommandShapeRule

**Identifiers:** `command.notFinalReadonly`, `command.hasPublicMethods`

CQRS commands — classes in a `\Command\` namespace that don't end in `Handler` and don't extend anything (so Symfony console commands are unaffected) — must be immutable data: `final readonly class` with no public methods besides `__construct()`.

> `Command <Class> must be declared as "final readonly class".`
> `Command <Class> must not declare public methods other than __construct().`

```php
// BAD
class CreateFooCommand
{
    public function __construct(public string $name) {}
    public function getName(): string { return $this->name; }
}

// GOOD
final readonly class CreateFooCommand
{
    public function __construct(public string $name) {}
}
```

---

## HandlerShapeRule

**Identifiers:** `handler.notFinalReadonly`, `handler.invalidShape`

Command handlers — classes in a `\Command\` namespace ending in `Handler` — must be `final readonly class` with exactly one public method: `__invoke()` (a public constructor is allowed).

> `Handler <Class> must be declared as "final readonly class".`
> `Handler <Class> must declare exactly one public method: __invoke().`

```php
// BAD
class CreateFooHandler
{
    public function __invoke(CreateFooCommand $command): void {}
    public function helper(): void {}
}

// GOOD
final readonly class CreateFooHandler
{
    public function __construct(private FooRepository $repository) {}
    public function __invoke(CreateFooCommand $command): void {}
}
```

---

## MessengerHandlerNamespaceRule

**Identifier:** `messenger.handlerNamespaceMismatch`

A class (or method) marked `#[AsMessageHandler]` must live in the same namespace as the message it handles. The message type is taken from the attribute's `handles:` argument when present, otherwise from the first parameter of the handling method (`method:` argument, defaulting to `__invoke()`). Handlers whose message type can't be resolved statically (no type hint, builtin type, union) are skipped.

> `Message handler <HandlerFqcn> and its message <MessageFqcn> must live in the same namespace.`

```php
// BAD — message lives in another namespace
namespace App\Module\Project\Messenger;

#[AsMessageHandler]
final readonly class ProvisionWorkspaceHandler
{
    public function __invoke(\App\Module\Workspace\ProvisionWorkspace $message): void {}
}

// GOOD — message and handler side by side
namespace App\Module\Project\Messenger;

#[AsMessageHandler]
final readonly class ProvisionWorkspaceHandler
{
    public function __invoke(ProvisionWorkspace $message): void {}
}
```

---

## BuildFormConstraintsRule

**Identifier:** `form.inlineConstraints`

Validation belongs on a DTO (as Validator attributes), not inline in `buildForm()` — whether or not the form type configures a `data_class`. An unmapped form that needs validation should introduce a `Request` DTO instead; forms with nothing to validate (CSRF-only, simple search forms) are unaffected.

The rule flags a `'constraints'` array key inside an options array passed to `$builder->add()` or `$builder->create()`, including nested option arrays such as `CollectionType`'s `entry_options`. A `'constraints'` key anywhere else in `buildForm()` (view variables, helper-call arguments) is ignored.

> `Form constraints must be declared on the DTO class (introduce a Request DTO for unmapped forms), not inline in buildForm().`

```php
// BAD
$builder->add('email', TextType::class, [
    'constraints' => [new NotBlank()],
]);

// GOOD — on the DTO instead
final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $email = null,
    ) {}
}
```

---

## FormDataClassNotEntityRule

**Identifier:** `form.dataClassIsEntity`

A form's `data_class` must be a DTO, never a Doctrine entity. The rule inspects `configureOptions()` and resolves the class referenced by `'data_class' => X::class`; if it carries `#[Entity]`, the rule reports.

> `Form data_class <Class> is a Doctrine entity. Use a DTO instead.`

```php
// BAD
$resolver->setDefaults(['data_class' => User::class]);       // #[ORM\Entity]

// GOOD
$resolver->setDefaults(['data_class' => RegisterRequest::class]);
```

---

## DtoRequestSuffixRule

**Identifier:** `dto.requestSuffix`

Non-form classes in a `Form\` namespace are form DTOs and must end in `Request`. Form types (subclasses of `AbstractType`) and abstract classes are exempt.

> `DTO class <Class> in a Form/ namespace must be named with a "Request" suffix (e.g. <Class>Request).`

```php
namespace App\Module\Project\Form;

// BAD
class CreateProject {}

// GOOD
class CreateProjectRequest {}
class CreateProjectFormType extends AbstractType {}
```

---

## NotBlankNullableRule

**Identifiers:** `dto.notBlankNotNullable`, `dto.notBlankDefaultNotNull`

A promoted property with `#[NotBlank]` must be nullable **and**, if it has a default, default to `null`. Symfony forms submit empty fields as `null`; with a non-nullable type that causes a `TypeError` before validation runs. The default must be `null` (not `''`/`0`) so "absent" is not conflated with "empty" — `NotBlank` rejects both, and consumers reading the validated value should assert presence (`$dto->name ?? throw new \LogicException(...)`) rather than fabricate an empty value.

> `Promoted property $<name> has #[NotBlank] but is not nullable. Use ?string or string|null.`
> `Promoted property $<name> has #[NotBlank] and is nullable but defaults to a non-null value. Default it to null (or omit the default) so "absent" is not conflated with "empty".`

```php
// BAD
public function __construct(
    #[Assert\NotBlank]
    public string $name = '',   // not nullable
    #[Assert\NotBlank]
    public ?string $email = '', // nullable but defaults to ''
) {}

// GOOD
public function __construct(
    #[Assert\NotBlank]
    public ?string $name = null,
    #[Assert\NotBlank]
    public ?string $email = null,
) {}
```

---

## EntityAsymmetricVisibilityRule

**Identifier:** `entity.privateSet`

Doctrine entities must not use `private(set)` asymmetric visibility on promoted constructor properties — Doctrine needs to write them. `$id` is exempt.

> `Entity property $<name> must not use private(set) asymmetric visibility. Use plain public visibility instead.`

```php
#[ORM\Entity]
class Project
{
    public function __construct(
        public private(set) mixed $id = null,   // OK — $id is exempt
        public private(set) string $name = '',  // BAD
        public string $slug = '',               // GOOD
    ) {}
}
```

---

## MigrationDescriptionRule

**Identifier:** `migration.emptyDescription`

Doctrine migrations must describe themselves: `getDescription()` must return a non-empty string literal.

> `Migration::getDescription() must return a non-empty string literal.`

```php
// BAD
public function getDescription(): string
{
    return '';
}

// GOOD
public function getDescription(): string
{
    return 'Create users table';
}
```

---

## RepositoryParameterNameRule

**Identifier:** `repository.parameterName`
**Configured by:** `gamache.repositoryNamingExcludedClasses`

Constructor parameters typed with a `*Repository` class must be named after the pluralized entity noun. The expected name is derived from the repository class name: strip the `Repository` suffix, lowercase the first letter, pluralize (`WorkspaceRepository` → `$workspaces`, `GitHubRepositoryRepository` → `$gitHubRepositories`). Only constructors are checked. Classes listed in `gamache.repositoryNamingExcludedClasses` (by default Doctrine's `EntityRepository`, `ServiceEntityRepository`, and `ObjectRepository`) are exempt, as is any class named exactly `Repository` (likely an entity, not a repository type).

> `Constructor parameter $<name> typed <Class> must be named $<expected> (pluralized entity noun).`

```php
// BAD
public function __construct(private ProjectRepository $projectRepo) {}

// GOOD
public function __construct(private ProjectRepository $projects) {}
```

---

## VoterNotReadonlyRule

**Identifier:** `voter.isReadonly`

Security voters must not be declared `readonly`. Use `final class`, not `final readonly class`.

> `Voter <Class> must not be readonly. Use "final class", not "final readonly class".`

```php
// BAD
final readonly class ProjectVoter extends Voter { /* … */ }

// GOOD
final class ProjectVoter extends Voter { /* … */ }
```

---

## TranslationCallSiteRule

**Identifier:** `translation.keyRequired`
**Configured by:** `gamache.translationCallSites`

String arguments at translation call sites must be translation keys, not prose. `TranslatorInterface::trans()` is checked out of the box; add your own call sites via configuration:

```neon
parameters:
    gamache:
        translationCallSites:
            - class: 'App\Service\Mailer'
              method: 'sendWelcome'
              argumentIndex: 0
```

A valid key matches `/^[a-z][a-z0-9]*([._-][a-z0-9]+)*$/` — lowercase, starting with a letter, segments separated by `.`, `_`, or `-` (e.g. `account.login.heading`).

> `Argument <n> of <context> must be a translation key (e.g. "account.login.heading"), got "<value>".`

```php
// BAD
$translator->trans('Welcome back');

// GOOD
$translator->trans('account.login.heading');
```

---

## TranslationAttributeRule

**Identifier:** `translation.keyRequired`
**Configured by:** `gamache.translationAttributeSites`

Same key format as [TranslationCallSiteRule](#translationcallsiterule), applied to attribute arguments — typically validator constraint messages:

```neon
parameters:
    gamache:
        translationAttributeSites:
            - class: 'Symfony\Component\Validator\Constraints\NotBlank'
              argumentNames: ['message']
            - class: 'Symfony\Component\Validator\Constraints\Length'
              argumentNames: ['minMessage', 'maxMessage']
```

> `Attribute argument "<name>" of #[<Attribute>] must be a translation key (e.g. "account.registration.validator.email_unique"), got "<value>".`

```php
// BAD
#[Assert\NotBlank(message: 'This field should not be blank.')]

// GOOD
#[Assert\NotBlank(message: 'account.validator.field_required')]
```

---

## EnumKebabCaseRule

**Identifier:** `enum.notKebabCase`

Values of string-backed enums must be kebab-case: `/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/`. Non-string enums are ignored.

> `Enum case value "<value>" must be kebab-case (e.g. "my-value").`

```php
// BAD
enum Status: string
{
    case InProgress = 'in_progress';
}

// GOOD
enum Status: string
{
    case InProgress = 'in-progress';
}
```

---

## PassThroughHelperRule

**Identifier:** `method.passThroughHelper`

A `private`/`protected` method whose entire body is a single call on one of the class's properties, with only trivially-forwardable arguments, adds indirection with no logic — inline the call at its call sites.

The discriminator is "does the body compute anything?", so the facade shape is flagged regardless of cosmetics:

- the receiver may be any `$this->prop` (promoted or not), including property-fetch chains (`$this->a->b`);
- arguments may be the method's parameters in any order (reordered, dropped), property fetches (`$this->x`), named arguments, or a spread of a parameter (`...$items`) — every one is available verbatim at the call site, so inlining is a copy of the body.

Not flagged (the body adds something):

- any **expression argument** — calls, arithmetic, concatenation, array literals, closures — is argument shaping;
- **literal/constant arguments** — binding a value is partial application and names a variant (`renderCompact()` vs `render(true)`);
- multi-statement bodies, conditionals, `public` methods (a deliberate API surface), static helpers, by-ref parameters, method calls in the receiver chain (`$this->a->getB()->…`);
- a `protected` method in a class that `extends` a parent: it may override or implement a parent contract, and a contract method cannot be inlined.

> `Method ChecklistPanelController::buildMatrix() is a one-liner pass-through to $this->checklistMatrixBuilder->build() — inline the call at its call sites.`

```php
// BAD — pure forwarding, no logic
private function buildMatrix(array $items): array
{
    return $this->checklistMatrixBuilder->build($items);
}

// BAD — reordering, property arguments, variadic spread: still no logic
private function notify(string $subject): void
{
    $this->notifier->send($this->recipient, $subject);
}

// GOOD — inline at the call site instead
$matrix = $this->checklistMatrixBuilder->build($items);

// Not flagged — the helper shapes its argument (real logic)
private function buildMatrix(array $items): array
{
    return $this->checklistMatrixBuilder->build(array_values($items));
}
```
