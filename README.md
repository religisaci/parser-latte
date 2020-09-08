## Installation

The only supported installation method is via [Composer](https://getcomposer.org). Run the following command to require parser-latte in your project:

```
composer require religisaci/parser-latte
```

## Getting started
```
<?php

use Religisaci\ParserLatte\Startup as ParserLatte;

ParserLatte::startup('way/to/templates/latte/');
```
Way to templates must end with a slash.