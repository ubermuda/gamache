module "app" {
  app_secret = var.app_secret

  extra_env = {
    APP_SECRET = { value = var.app_secret, type = "SECRET" }
  }
}
