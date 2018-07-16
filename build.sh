#!/bin/bash

DIR="./tmp/ether-and-erc20-tokens-woocommerce-payment-gateway/"

rm -rf $DIR

mkdir -p $DIR

# min.js file
grunt uglify

rm -rf vendor/ composer.lock
composer install --no-dev

cp -r \
	assets/ \
	css/ \
	fonts/ \
	img/ \
	js/ \
	languages/ \
	src/ \
	templates/ \
	vendor/ \
	autoload.php \
	readme.txt \
	ether-and-erc20-tokens-woocommerce-payment-gateway.php \
	$DIR

rm -rf $DIR/css/*.map
rm -rf $DIR/js/*.map
rm -rf $DIR/vendor/*/*/composer.json
rm -rf $DIR/vendor/*/*/.git
rm -rf $DIR/vendor/*/*/tests
rm -rf $DIR/vendor/*/*/Tests
rm -rf $DIR/vendor/*/*/test
rm -rf $DIR/vendor/*/*/Test
rm -rf $DIR/vendor/*/*/examples
rm -rf $DIR/vendor/*/*/docker
rm -rf $DIR/vendor/*/*/docs
rm -rf $DIR/vendor/*/*/travis
rm -rf $DIR/vendor/*/*/.travis.yml
rm -rf $DIR/vendor/*/*/.editorconfig
rm -rf $DIR/vendor/*/*/CHANGELOG.md
rm -rf $DIR/vendor/*/*/README.md
rm -rf $DIR/vendor/*/*/UPGRADING.md
rm -rf $DIR/vendor/*/*/LICENSE
rm -rf $DIR/vendor/*/*/Makefile
rm -rf $DIR/vendor/*/*/appveyor.yml
rm -rf $DIR/vendor/*/*/phpunit.xml
rm -rf $DIR/vendor/*/*/phpunit.xml.dist
rm -rf $DIR/vendor/*/*/.github/
rm -rf $DIR/vendor/*/*/.gitattributes
rm -rf $DIR/vendor/*/*/.gitignore
rm -rf $DIR/vendor/bin

cd tmp
rm -f ../ether-and-erc20-tokens-woocommerce-payment-gateway.zip
zip -r ../ether-and-erc20-tokens-woocommerce-payment-gateway.zip ether-and-erc20-tokens-woocommerce-payment-gateway/

cd ..
rm -rf tmp
