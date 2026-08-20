variable "region" {
  type        = string
  description = "App Platform region slug."
}

variable "db_cluster_region" {
  type    = string
  default = "tor1"
}

variable "export_storage_key" {
  type      = string
  sensitive = true
}
