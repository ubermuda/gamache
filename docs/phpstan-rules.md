# PHPStan rules

Including `vendor/ubermuda/gamache/extension.neon` in your `phpstan.neon` registers all 22 rules (see the [README](../README.md#phpstan-rules) for setup and parameters).

Every error carries an identifier, so you can opt out of a single rule with PHPStan's `ignoreErrors`:

```neon
parameters:
    ignoreErrors:
        - identifier: enum.notKebabCase
```

All rules live in the `Gamache\PHPStan` namespace.

**Controllers:** [ControllerParentRule](#controllerparentrule) · [ControllerSingleActionRule](#controllersingleactionrule) · [ControllerRouteAttributeRule](#controllerrouteattributerule) · [ControllerTemplateNameRule](#controllertemplatenamerule) · [DenyAccessUnlessGrantedRule](#denyaccessunlessgrantedrule) · [IsGrantedNoFullyAuthRule](#isgrantednofullyauthrule)
**Routing:** [RouteNoUnderscorePrefixRule](#routenounderscoreprefixrule) · [RouteParamCamelCaseRule](#routeparamcamelcaserule)
**CQRS:** [CommandShapeRule](#commandshaperule) · [HandlerShapeRule](#handlershaperule)
**Messenger:** [MessengerHandlerNamespaceRule](#messengerhandlernamespacerule)
**Forms & DTOs:** [BuildFormConstraintsRule](#buildformconstraintsrule) · [FormDataClassNotEntityRule](#formdataclassnotentityrule) · [DtoRequestSuffixRule](#dtorequestsuffixrule) · [NotBlankNullableRule](#notblanknullablerule)
**Entities & migrations:** [EntityAsymmetricVisibilityRule](#entityasymmetricvisibilityrule) · [MigrationDescriptionRule](#migrationdescriptionrule) · [RepositoryParameterNameRule](#repositoryparameternamerule)
**Security:** [VoterNotReadonlyRule](#voternotreadonlyrule)
**Translations:** [TranslationCallSiteRule](#translationcallsiterule) · [TranslationAttributeRule](#translationattributerule)
**Misc:** [EnumKebabCaseRule](#enumkebabcaserule)

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

- `$this->denyAccessUnlessGranted(...)` calls
- `$this->createAccessDeniedException(...)` calls
- `new AccessDeniedHttpException(...)` (`Symfony\Component\HttpKernel\Exception`)
- `new AccessDeniedException(...)` (`Symfony\Component\Security\Core\Exception`)

> `AppController::__invoke() must not <call …/instantiate …>. Use #[IsGranted] with a Voter constant and subject. To exempt dynamic-subject controllers, add "access is enforced per-branch" to the class docblock.`

**Exemption:** when the access decision depends on runtime data and can't be expressed as an attribute, add `access is enforced per-branch` to the class docblock (or a comment on the class).

```php
// BAD
class DeleteProjectController extends AppController
{
    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // …
    }
}

// GOOD
#[IsGranted(ProjectVoter::DELETE, subject: 'project')]
class DeleteProjectController extends AppController
{
    public function __invoke(Project $project): Response { /* … */ }
}

// GOOD — exempted
/**
 * access is enforced per-branch.
 */
class BranchController extends AppController
{
    public function __invoke(string $branch): Response
    {
        $this->denyAccessUnlessGranted('branch_access', $branch);
        // …
    }
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

When a form type has a `data_class`, validation belongs on the DTO (as Validator attributes), not inline in `buildForm()`. The rule flags any `'constraints'` array key inside `buildForm()` of a form type that configures a `data_class`.

> `Form constraints must be declared on the DTO class, not inline in buildForm().`

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
