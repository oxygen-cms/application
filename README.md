# Oxygen CMS - Application Template

Based off the [Laravel 12.x](https://github.com/laravel/laravel/tree/12.x)
application template.

## Building and testing the frontend

```bash
pushd resources/frontend
npx vite build
popd
php artisan vendor:publish --tag=public --force
```
