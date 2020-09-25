### Sumsub

PHP client library for accessing the Sum&Substance's API

Read more about the platform at https://developers.sumsub.com/api-reference/#introduction

### Installation
To install sumsub client, run the command below and you will get the latest version
```bash
composer require mihailgolban/sumsub
```
Or alternatively, include a dependency for mihailgolban/sumsub in your composer.json file. For example:
```bash
{
    "require": {
        "mihailgolban/sumsub": "1.*"
    }
}
```
### Configuration
Set in the .env environment file following keys:
```env
sumsub.secretKey = <SECRET_KEY>
sumsub.token = <TOKEN>
sumsub.baseUrl = <BASE_URL>
```

### License
The MIT License (MIT). Please see [License File](https://opensource.org/licenses/MIT) for more information.
