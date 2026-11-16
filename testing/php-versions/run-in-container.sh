#!/bin/sh
# Runs inside a test container (see run-all-php-versions.sh). The repository is mounted read-only at /src, so it is
# copied first, so that the tests can't change it. It's copied into /tmp, as the container runs as the host's user,
# rather than root, who can't write to /.
# Exits with 0 if everything passed, or 1 if anything failed.

appDir=/tmp/app
cp -r /src "$appDir"
failed=0
phpVersion=$(php -r 'echo PHP_VERSION;')

echo "=== lint (PHP ${phpVersion})"
numLintFailures=0

for file in $(find "$appDir/src" "$appDir/testing" -name '*.php')
do
    if ! output=$(php -l "$file" 2>&1)
    then
        echo "$output"
        numLintFailures=$((numLintFailures + 1))
    fi
done

echo "lint failures: ${numLintFailures}"
[ "$numLintFailures" -eq 0 ] || failed=1

echo "=== smoke tests"
php -d zend.assertions=1 -d assert.exception=1 "$appDir/testing/main.php" || failed=1

exit $failed
