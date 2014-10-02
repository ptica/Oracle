Fork of https://bitbucket.org/odin88/cakephp-2.0-oracle

will see how it fares

* [x] adding composer.json
* [x] reading & saving associations
* [x] delete problems
* [ ] transactions problems
* [x] caching problems

I configure the connection as:

```php
public $default = array(
                'datasource' => 'Oracle.Oracle',
                'driver' => 'oracle',
                'connect' => 'oci_pconnect',
                'persistent' => false,
                'host' => 'localhost',
                'login' => 'username',
                'password' => 'password',
                'database' => 'example.com/sid',
                'prefix' => '',
        );
```
