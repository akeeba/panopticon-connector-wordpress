## ℹ️ What is this?

This is the connector plugin for [Akeeba Panopticon](https://github.com/akeeba/panopticon), our self-hosted site monitoring software. You need to install it on your WordPress site to be able to monitor it with Akeeba Panopticon.

ℹ️ If you have a Joomla! 3 site please [look at the Joomla 3 connector's repository](https://github.com/akeeba/panopticon_connector_j3/releases/latest) instead.

ℹ️ If you have a Joomla! 4 or later site please [look at the Joomla 4 or later connector's repository](https://github.com/akeeba/panopticon-connector/releases/latest) instead.

## 🔎 Release highlights

* Initial release

## 🖥️ System Requirements

* WordPress 5.0 or later
* PHP versions 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, and 8.3

## 🧑🏽‍💻 Important note about the WordPress REST API

Akeeba Panopticon Connector uses the WordPress REST API (the `/wp-json` URL on your site).

This is not a real folder on your site; it's a route. You must have a properly set up `.htaccess` file on your site for this route to work.

Some sites have disabled the WordPress REST API. Typically, this is done with a third party plugin, a security plugin, changes in the `.htaccess` file, or custom code. Please try accessing the `/wp-json` URL of your site. If you get a JSON reply with a `routes` collection you're all set. If not, you need to re-enable the REST API – how to do that depends on how you disabled it in the first place.

### Security and privacy of the WordPress REST API

People disable the REST API because they're told it has security and/or privacy issues. This is not entirely true. The concern more accurately refers to the following REST routes being accessible even without a login:
* `wp-json/wp/v2/users` lists usernames and avatars, but not passwords or emails. This is public information, but you may want to keep it private.
* `wp-json/wp/v2/posts` lists all public posts, including those normally hidden, only available if you know the URL to it. This is public information, but some users may be abusing this feature to share private / sensitive information even though this is not what this feature is designed for.
* `wp-json/wp/v2/pages` does the same as above, but for pages.

Moreover, the REST API can potentially be used for brute forcing a user's application passwords (NOT their regular log-in password!). The HTTP Basic Authentication provides a convenient way to check if a username and password combination works. However, this is a. slow; b. unlikely to work; and c. only a concern if the user _does_ use application passwords. Otherwise, this is not even remotely a security concern. All other API authentication methods are even less vulnerable to a brute-force attack.

You can neutralise all these concerns **WITHOUT** disabling the WordPress REST API in its entirety. Instead, you can use the following code in your .htaccess file. If you are using Admin Tools Professional's .htaccess Maker feature you can add this custom rule above to its “Custom .htaccess rules at the top of the file”.

```apacheconf
RewriteRule ^wp-json/wp/v[\d]+/(users|posts|pages)(/|$) - [R=403,L]
RewriteCond %{HTTP:Authorization} ^Basic
RewriteRule ^wp-json(/|$) - [R=403,L]
```

Please note that using these rules will prevent [WP-CLI](https://wp-cli.org/) from being able to _remotely_ manage your site; WP-CLI will still work _locally_. Any other software or plugin using the aforementioned routes, or HTTP Basic Authentication to remotely access your site will also fail to work properly.

## 📋 CHANGELOG

* ✏️ Option to disable system information collection.
* 🐞 Does not recognise Akeeba Backup version before 8.0
* 🐞 Compatibility issues with ancient WordPress 5.0 to 5.5 versions

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix