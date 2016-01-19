![alt text](https://plugins.svn.wordpress.org/migrate-wufoo-to-gravity-forms//assets/banner-772x250.png)

# Migrate Wufoo To Gravity Forms #

* **Contributors:** [rtcamp] (http://profiles.wordpress.org/rtcamp), [saurabhshukla] (http://profiles.wordpress.org/saurabhshukla), [nitun.lanjewar] (http://profiles.wordpress.org/nitun.lanjewar),

* **License:** [GPL v2 or later] ( http://www.gnu.org/licenses/gpl-2.0.html)

* **Donate Link:** http://rtcamp.com/donate/

"Wufoo to Gravity Forms Importer" imports form entries, comments and attachments from your Wufoo account to Gravity Forms.

## Description ##

`Wufoo to Gravity Forms Importer` imports form entries, comments and attachments from your Wufoo account to Gravity Forms. It also comes with a “mapping” interface where you can map wufoo form fields to gravity form fields.

***Please note*** that actual Wufoo forms are not imported. You need to create forms in Gravity Forms manually.

# This plugin can import #

* Form entries (i.e. form submission data)
* Notes/comments added on form from Wufoo backend
* Attachments uploaded to as part of Wufoo form fields
* This plugin also comes with a “mapping” interface where you can map wufoo form fields to gravity form fields. Mapping interface also help you map between Wufoo users and WordPress users.

# This plugin can NOT #

* Import actual Wufoo form. You need to create forms in gravity forms manually and use “mapping” interface to map wufoo form fields to gravity form fields. This is useful if you want to import multiple Wufoo forms into a single Gravity Form.
* Sync form data on-the-go. You can manually import previously imported form and only new entries will be imported as long as you choose same Gravity Form. But there is no automated provision to import new Wufoo form entries.

# Features #

* Intuitive import wizard
* Fail-safe import process that can be resumed
* Mapping of users and form fields and imports all your data as it is
* Imports large amount of data
* Uses latest Wufoo API (version 3.0)
* Safe from daily [Wufoo API Limit](http://help.wufoo.com/articles/en_US/SurveyMonkeyArticleType/Wufoo-REST-API-V3#restrictions) of 5000 requests

## Installation ##

Follow these simple steps to get Wufoo To Gravity Forms Importer plugin.

Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
* Activate the plugin through the 'Plugins' screen in WordPress
* Use the Settings->Plugin Name screen to configure the plugin
* (Make your instructions match the desired user flow for activating and installing your plugin. Include any steps that might be needed for explanatory purposes)



## Frequently Asked Questions ##

#### Q. I have a very large amount of data on my Wufoo account, can I import all of it?

Yes, this plugin has no limitation for import and if you hit you Daily API Limit import will be resumed next day

#### Q. How can I use Multiple Wufoo APIs with this plugin?

You just have to enter your Wufoo API key in the plugin and it will import and correctly map all the data according to your choice.

#### Q. Where do I find my Wufoo API key?

It is a 16 digit code, which applies for all your Wufoo forms. To obtain your key, log in to your Wufoo account, head to the Form Manager or Forms tab, and click the Code button beneath the name of any form. This will take you to the code manager. Next, click the API Information button to view your API key.

#### What if I don’t want to import certain fields?

Sure, you can do that if you don’t map the particular field with any of Gravity Form fields.

#### Do I have to use same fields in the gravity form as I was using in Wufoo Form?

No, you don’t need to match every form field. Just make sure to map all the desired fields for importing data.



## Screenshots ##

You Please check [Wiki Page](https://github.com/rtCamp/migrate-wufoo-to-gravity-forms/wiki/user-guide)


## Changelog ##

#### 1.0 
* Initial relesae
* fixed entry import tracking
* fixed for no comments on wufoo

#### 1.0 ####
Requires WordPress 3.0 or higher.



## Credits ##

[Wufoo API](http://help.wufoo.com/articles/en_US/SurveyMonkeyArticleType/Wufoo-REST-API-V3#restrictions)
