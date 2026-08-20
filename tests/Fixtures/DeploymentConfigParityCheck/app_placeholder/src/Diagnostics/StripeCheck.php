<?php
final class StripeCheck
{
    public function __construct(
        #[Autowire('%env(default::STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
    ) {
    }
}
