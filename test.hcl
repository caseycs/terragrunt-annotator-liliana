include "provider" {
  // Liliana file:///./../../../../provider-aws.hcl
  path = find_in_parent_folders("provider-aws.hcl")
  path = "a"

  db_name            = "${local.project.name}_${replace(local.application.name, "-", "_")}"

  val2 = -3
  val2 = - dependency.vpc[0].fds
}

# terraform {
#   "a" = true
#   1 = true
#   versioning = {
#     status = false
#     abc = "fds"
#     d = {
#       a = "fd"
#     }
#   }

#   val1 = func1()[0]
#   val2 = var1["fds"]
#   val2 = 3
#   val2 = "fds"
#   subnet_id = dependency.vpc.outputs.public_subnets[0].fds

#   lifecycle_rule = ["a"]
# }

# terraform {
#     source = "${get_repo_root()}/terragrunt/modules/github/repos///."
# }

# inputs = {
#     name = "${include.global_vars.locals.env_prefix}-lands-player-events.fifo"
# }

# include "root" {
#     // Liliana file:///./../../../../../terragrunt.hcl
#     path1 = "fds"
#     path2 = input1
#     path3 = input2.key1.key2
#     path4 = find_in_parent_folders("fsa", "afds")
#     path4 = find_in_parent_folders(find_in_parent_folders("faraway-users.hcl")).abc
#     contents  = <<EOF
# BLA1
# BLA2
# EOF
# }

# include "root" {
#     // Liliana file:///./../../../../../terragrunt.hcl
#     path = find_in_parent_folders()
# }

# locals {
#     faraway_users  = read_terragrunt_config(find_in_parent_folders("faraway-users.hcl")).inputs
#     faraway_groups = read_terragrunt_config(find_in_parent_folders("faraway-groups.hcl")).inputs
# }

# dependency "sqs-4" {
#     // Liliana file:///./../../aws-sqs/lands-quests-queue/terragrunt.hcl
#     config_path = "../../aws-sqs/lands-quests-queue"
# }