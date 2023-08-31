# laravel-transcription-onramp-lab-extension

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![CircleCI](https://circleci.com/gh/OnrampLab/laravel-transcription-onramp-lab-extension.svg?style=shield)](https://circleci.com/gh/OnrampLab/laravel-transcription-onramp-lab-extension)
[![Total Downloads](https://img.shields.io/packagist/dt/onramplab/laravel-transcription-onramp-lab-extension.svg?style=flat-square)](https://packagist.org/packages/onramplab/laravel-transcription-onramp-lab-extension)

An extension of [Laravel Transcription package](https://github.com/OnrampLab/laravel-transcription) built for OnrampLab

## Requirements

- PHP >= 8.1
- composer

## Features

- Provide custom classes implemented for transcription use case in OnrampLab
  - audio transcriber

## Installation

```bash
composer require onramplab/laravel-transcription-onramp-lab-extension
```

## Usage

### Audio Transcriber

The `OnrampLabWhisperAudioTranscriber` class is using our company's AWS Lambda function to transcribe audio with Open AI Whisper. You can check out [repository](https://github.com/OnrampLab/open-ai-whisper-lambda-function) for more information.

In order to use this transcriber, you should add transcriber driver configuration in _Available Transcribers_ section of your `config/transcription.php` configuration file.

```php
'transcription' => [
  'transcribers' => [
      'onramp_lab_whisper' => [
          'driver' => 'onramp_lab_whisper',
          'access_key' => env('AWS_ACCESS_KEY_ID'),
          'access_secret' => env('AWS_SECRET_ACCESS_KEY'),
          'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
      ],
  ],
],
```

> The driver name should always be **onramp_lab_whisper**.

## Running Tests:

    php vendor/bin/phpunit

 or

    composer test

## Code Sniffer Tool:

    php vendor/bin/phpcs --standard=PSR2 src/

 or

    composer psr2check

## Code Auto-fixer:

    composer psr2autofix
    composer insights:fix
    rector:fix

## Building Docs:

    php vendor/bin/phpdoc -d "src" -t "docs"

 or

    composer docs

## Changelog

To keep track, please refer to [CHANGELOG.md](https://github.com/onramplab/laravel-transcription-onramp-lab-extension/blob/master/CHANGELOG.md).

## Contributing

1. Fork it.
2. Create your feature branch (git checkout -b my-new-feature).
3. Make your changes.
4. Run the tests, adding new ones for your own code if necessary (phpunit).
5. Commit your changes (git commit -am 'Added some feature').
6. Push to the branch (git push origin my-new-feature).
7. Create new pull request.

Also please refer to [CONTRIBUTION.md](https://github.com/onramplab/laravel-transcription-onramp-lab-extension/blob/master/CONTRIBUTION.md).

## License

Please refer to [LICENSE](https://github.com/onramplab/laravel-transcription-onramp-lab-extension/blob/master/LICENSE).
