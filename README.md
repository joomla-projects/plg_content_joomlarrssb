# Ridiculously Responsive Social Sharing Buttons for joomla.org

This is a Joomla! plugin which adds social sharing buttons and metadata optimized for Open Graph and Twitter to com_content items.

## Requirements

* Joomla! 3.9 or newer
* PHP 7.2.5 or newer

## Support

This plugin is primarily designed for use on the `joomla.org` website network and as such priority is given to the use cases there.  Additional features or use cases will be considered on a case-by-case basis.

## Layout/Media Overrides

The plugin's media and layouts may be overridden following the Joomla! override conventions.

To override the layout file, copy the `tmpl/default.php` file to `templates/<template_name>/html/plg_content_joomlarrssb/default.php`

To override the CSS files, copy the `media/css/*.css` files to `templates/<template_name>/css/joomlarrssb/*.css`

To override the JavaScript files, copy the `media/js/*.js` files to `templates/<template_name>/js/joomlarrssb/*.js`

### Optional RTL CSS

The plugin's default layout supports inclusion of a RTL CSS file if need be (one is not shipped with the plugin by default).  You can add RTL CSS by placing a file at `templates/<template_name>/css/joomlarrssb/joomla-rtl.css`.

### Release steps

- `build/build.sh`
- `git commit -am 'prepare JoomlaRSSB 3.0.x'`
- `git tag -s '3.0.x' -m 'JoomlaRSSB 3.0.x'`
- `git push origin --tags`
- create the release on GitHub
- `git push origin master`
