function green() {
  printf '\033[1;32m%s\033[0m\n' "$1"
}

function red() {
  printf '\x1b[31m %s \x1b[0m\n' "$1"
}

function grey() {
  printf '\033[1;36m%s \033[0m\n' "$1"
}
