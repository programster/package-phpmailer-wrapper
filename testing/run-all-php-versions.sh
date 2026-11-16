#!/bin/sh
# Runs lint and the smoke tests (testing/main.php) on every supported version of PHP, each in its own Docker
# container, then PHPStan once. This catches code that only works on newer versions of PHP than the minimum this
# package supports. The tests send their emails to a fake SMTP server inside the container, so they need no network.
#
# Usage:
#   testing/run-all-php-versions.sh                # test every supported version
#   testing/run-all-php-versions.sh 8.3 8.5        # test only these versions
#   testing/run-all-php-versions.sh --rebuild      # rebuild the Docker images first (e.g. for the latest patch release)
#
# Requires Docker, and "composer install" to have been run, as the tests use the vendor/ directory. composer.json sets
# the platform to PHP 8.2, so that the installed dependencies work on every supported version.
# Exits with 0 if every version passed, or 1 if any failed.

SUPPORTED_VERSIONS="8.2 8.3 8.4 8.5"

scriptDir=$(cd "$(dirname "$0")" && pwd)
repoDir=$(dirname "$scriptDir")
rebuild=0
versions=""

# The containers run as this user, rather than root, as root can read any file, so the tests of an unreadable file
# would be skipped.
user="$(id -u):$(id -g)"

for arg in "$@"
do
    if [ "$arg" = "--rebuild" ]
    then
        rebuild=1
    else
        versions="$versions $arg"
    fi
done

if [ -z "$versions" ]
then
    versions=$SUPPORTED_VERSIONS
fi

if [ ! -f "$repoDir/vendor/autoload.php" ]
then
    echo "vendor/autoload.php is missing. Please run \"composer install\" first."
    exit 1
fi

failedVersions=""
phpstanFailed=0

for version in $versions
do
    image="phpmailer-wrapper-php${version}-test"
    echo
    echo "################ PHP ${version}"

    if [ "$rebuild" -eq 1 ] || ! docker image inspect "$image" > /dev/null 2>&1
    then
        echo "building ${image}..."

        if ! docker build -q --pull --build-arg PHP_VERSION="$version" -t "$image" "$scriptDir/php-versions" > /dev/null
        then
            echo "FAILED to build the image for PHP ${version}"
            failedVersions="$failedVersions $version"
            continue
        fi
    fi

    # The tests need no network access (the fake SMTP server listens on the container's own 127.0.0.1), so it's
    # switched off.
    if ! docker run --rm --network none --user "$user" -v "$repoDir":/src:ro "$image" \
        sh /src/testing/php-versions/run-in-container.sh
    then
        failedVersions="$failedVersions $version"
    fi
done

# Static analysis only needs to run once. It runs on the lowest version tested, as that is the most likely to differ.
lowestVersion=$(echo $versions | tr ' ' '\n' | sort -V | head -n 1)
echo
echo "################ PHPStan (PHP ${lowestVersion})"

if ! docker run --rm --network none --user "$user" -v "$repoDir":/src:ro "phpmailer-wrapper-php${lowestVersion}-test" \
    sh -c 'cp -r /src /tmp/app && cd /tmp/app && php vendor/bin/phpstan --no-progress --memory-limit=1G'
then
    phpstanFailed=1
fi

echo
if [ -z "$failedVersions" ] && [ "$phpstanFailed" -eq 0 ]
then
    echo "All passed:" $versions
    exit 0
else
    [ -z "$failedVersions" ] || echo "FAILED on PHP:" $failedVersions
    [ "$phpstanFailed" -eq 0 ] || echo "FAILED PHPStan"
    exit 1
fi
