# Oxygen CMS - Application Template

Based off the [Laravel 12.x](https://github.com/laravel/laravel/tree/12.x)
application template.

## Oxygen Backend

The main backend functionality comes from the [oxygen/core](https://github.com/oxygen-cms/core) package on Packagist, plus several others.

## Oxygen Frontend

The frontend is mostly from this npm package: [@oxygen-cms/ui](https://github.com/oxygen-cms/ui), but it's integrated into the Laravel application template under `resources/frontend`.

If you want to add any custom UI modules/pages/functionality, you can register them in [resources/frontend/main.js]

### Building and testing the frontend

```bash
pushd resources/frontend
npx vite build
popd
php artisan vendor:publish --tag=public --force
```
