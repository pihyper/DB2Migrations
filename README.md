# DB2Migrations

This is a fork of [adamkearsley/laravel-convert-migrations](adamkearsley/laravel-convert-migrations):
* It adds support for **Laravel 5.4.***.
* Fixes some bugs (e.g: timestamp default values not working).
* Each table now has it's own migration file, instead of one file containing all the migrations.

---

This is an artisan command to convert your current SQL database schema into Laravel 5.4.* Migration files (one for each table). It'll come really handy when you have started a Laravel project without using migrations, if you're migrating an old app to Laravel, or maybe you used a database designer to generate SQL.

## Installation

1. Add the package to your composer.json file and run `composer update`:

```json
"require": {
    "d3vr/db2migrations": "dev-master"
}
```

2. Add `'d3vr\DB2Migrations\DB2MigrationsServiceProvider::class'` to your `config/app.php` file, inside the `providers` array.

## Usage

Now it's as easy as running `php artisan convert:migrations myDatabaseName`. Wait a few seconds and, automagically, you'll have the new migration files in `app/database/migrations`.

**Ignoring Tables**

You can even ignore tables from the migration if you need to. Just use the `ignore` option and separate table names with a comma: `php artisan convert:migrations --ignore="table1, table2"`.

## Credits

Credits go to "bruceoutdoors" [original class](https://gist.github.com/bruceoutdoors/9166186).