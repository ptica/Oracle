# CakePHP 2.x Oracle driver
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

## Tips

* for autoincrement use sequences with triggers

```sql
CREATE SEQUENCE "configurations_seq"
MINVALUE 1
MAXVALUE 999999999999999999999999999
INCREMENT BY 1
START WITH 1
NOCACHE
NOORDER
NOCYCLE ;
/

CREATE OR REPLACE TRIGGER "configurations_ai"
BEFORE INSERT ON "configurations"
FOR EACH ROW
BEGIN
	select "configurations_seq".nextval into :new."id" from dual;
END;
/

ALTER TRIGGER "configurations_ai" ENABLE;
```

* virtualFields have to be without spaces after commas to work around issue #14
```php
$this->virtualFields['url'] = 'CONCAT(\'/bookings/view/\',Booking.id)';
```

* avoid NOT NULL with empty string defaults - leads to "cannot insert NULL"
