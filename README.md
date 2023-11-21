# Prune Large Database Tables Data

```php
DatabasePruningFactory::forQuery(DB::table('table')->where('column', $value))
          ->chunk(700)
          ->displayName($name = 'DeleteTransactions')
          ->usingConnection($connection) // specify sql connection to perform the deletion. 
          ->getBatch()
          ->name($name)
          ->onConnection('your job connection')
          ->onQueue('your job queue')
          ->dispatch();
```
