# speedyspec/wordpress-org

The [speedyspec](https://github.com/SpeedySpec/speedyspec-cli) CLI source plugin for wordpress.org's plugin and
theme directories. With it, and an app plugin such as
[speedyspec/wordpress](https://github.com/SpeedySpec/speedyspec-cli-wordpress), `speedyspec plugin install akismet
--install blog` and `speedyspec theme install twentytwentyfive --install blog` fetch from wordpress.org.

It answers `speedyspec_extension_sources` with one `ExtensionSource`, `wordpress.org`. Its `resolve` asks
wordpress.org's API (`plugins/info/1.2` and `themes/info/1.2`) for the slug and gives the latest version's archive,
or the one asked for (`akismet@5.3.4`). A slug wordpress.org does not know, or a version it does not have, gives
nothing, so the next source is asked. wordpress.org publishes no checksums, so the archives are fetched over https
without one.

It talks to the API with Guzzle. The plugin commits its `composer.lock`, and `speedyspec self plugin install`
installs Guzzle from it with speedyspec's own composer, written in Rust. From a checkout used with
`--plugin-dir`, run `speedyspec composer install --working-dir <checkout>` first.

`SPEEDYSPEC_WORDPRESS_ORG_API` replaces `https://api.wordpress.org` (the tests point it at a local server).

## Developing

```bash
composer install
vendor/bin/pest
SPEEDYSPEC_BIN=/path/to/speedyspec tests/cli.sh
```
