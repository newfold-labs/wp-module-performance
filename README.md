<a href="https://newfold.com/" target="_blank">
    <img src="https://newfold.com/content/experience-fragments/newfold/site-header/master/_jcr_content/root/header/logo.coreimg.svg/1621395071423/newfold-digital.svg" alt="Newfold Logo" title="Newfold Digital" align="right" 
height="42" />
</a>

# WordPress Performance Module
[![Version Number](https://img.shields.io/github/v/release/newfold-labs/wp-module-performance?color=21a0ed&labelColor=333333)](https://github.com/newfold/wp-module-performance/releases)
[![License](https://img.shields.io/github/license/newfold-labs/wp-module-performance?labelColor=333333&color=666666)](https://raw.githubusercontent.com/newfold-labs/wp-module-performance/master/LICENSE)

A module for managing caching functionality.

## Installation

### 1. Add the Newfold Satis to your `composer.json`.

 ```bash
 composer config repositories.newfold composer https://newfold-labs.github.io/satis
 ```

### 2. Require the `newfold-labs/wp-module-performance` package.

 ```bash
 composer require newfold-labs/wp-module-performance
 ```

[More on NewFold WordPress Modules](https://github.com/newfold-labs/wp-module-loader)


## TODO

- Create a cron to clear old static cached files
- Implement UI component for handling performance (and action/reducer)
