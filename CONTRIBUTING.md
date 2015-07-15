Contributing
-------------

Before you contribute code to php-console, please make sure it conforms to the PSR-2 coding standard and that the php-console unit tests still pass. The easiest way to contribute is to work on a checkout of the repository, or your own fork. If you do this, you can run the following commands to check if everything is ready to submit:

    cd php-console
    composer update
    vendor/bin/phpcs --report=full --extensions=php -p --standard=build/phpcs .

Which should give you no output, indicating that there are no coding standard errors. And then:

    phpunit

Which should give you no failures or errors. You can ignore any skipped tests as these are for external tools.