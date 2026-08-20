module "app" {
  extra_env = {
    APP_SECRET = { value = var.app_secret }
  }
}
