

```sh
# PHP 8.1+ required
cp config.example.php config.php

# choose SQLITE (default) or set POSTGRES DSN in config.php
php -S 127.0.0.1:8080 -t public
```