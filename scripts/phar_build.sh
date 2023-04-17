#!/bin/bash

set -e

echo "Installing composer dependencies with --no-dev..."
composer install --no-dev

VERSION=$(cat .version)
## if not in the context of a github action, lable the build as
## a dev build
if [[ -z "${GITHUB_RUN_ID}" ]]
then
    echo "$VERSION+dev" > .version
fi

BUILD_VERSION=$(cat .version)

echo "Building terminus.phar...${BUILD_VERSION}"
box compile
echo "terminus.phar file has been created successfully!"

if [[ -n "${TERMINUS_ON_PHAR_COMPLETE_REINSTALL_COMPOSER_WITH_DEV}" ]]; then
    echo "Reinstalling composer dependencies with --dev..."
    composer install --dev
fi

## restore version to correct value
echo ${VERSION} > .version
