## GP social login for Wordpress

Allows users to log in to Wordpress using Google+ or Facebook.
Adds a configuration panel under Settings where users can set API keys.
Upon successful login, depending on the service, users will be redirected to either:
```
-- www.mywebsite.com/facebookcallback/
-- www.mywebsite.com/googlecallback/
```
* Trailing slash is mandatory.
After that the user will be sent to the destination url set in the Settings panel.

## Usage:
This plug-in exposes two shortcodes:
```
[gp_social_google]
[gp_social_facebook]
```
Both can be called with just the shortcode or by using a message parameter, which will be the text displayed in the link, e.g.:
```
[gp_social_google message="Click here to login"]
```
## Theming
Both links have a common CSS class: "gp_sociallogin".
Google's login link has a specific class: "google".
Facebook's login link has a specific class: "facebook".

