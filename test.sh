export $(grep -v '^#' .env | xargs)
php vendor/bin/phpunit tests --coverage-html coverage-report
