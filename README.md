# Friendable

[![Latest Stable Version](https://poser.pugx.org/gregoryduckworth/friendable/v/stable)](https://packagist.org/packages/gregoryduckworth/friendable) [![Total Downloads](https://poser.pugx.org/gregoryduckworth/friendable/downloads)](https://packagist.org/packages/gregoryduckworth/friendable) [![Build Status](https://api.travis-ci.org/gregoryduckworth/friendable.png?branch=master)](https://api.travis-ci.org/gregoryduckworth/friendable) [![License](https://poser.pugx.org/gregoryduckworth/friendable/license)](https://packagist.org/packages/gregoryduckworth/friendable)

Add the possibility of friends quickly with the use of this trait.

## Install

Via Composer

``` bash
$ composer require gregoryduckworth/friendable
```
And then include the service providero within `config/app.php`

```php
'providers' => [
    ...
    GregoryDuckworth\Friendable\FriendableServiceProvider::class,
    ...
];
```

At lastly you need to publish and run the migration.

```
php artisan vendor:publish && php artisan migrate
```

## Usage

```php
Add the Trait to the User Model

use GregoryDuckworth\Friendable\Traits\Friendable;

class User extends Model
{
    use Friendable;
    ...
}
```

## Examples

#### List all the users confirmed friends
```php
    $user->confirmedFriends();
```

#### List all the users pending friends
```php
    $user->pendingFriends();
```

#### List all the friends waiting to approve the user
```php
    $user->awaitingApproval();
```

#### Request the friendship of another user
```php
    $user->requestFriendship(Model $friend);
```

#### Confirm the friendship
```php
    $user->confirmFriendship(Model $friend);
```

#### Remove the friendship
```php
    $user->removeFriendship(Model $friend);
```

## Todo

* Add tests (inc travis builds)
* Ability to block friends

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
