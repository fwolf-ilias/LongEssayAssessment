# LongEssayAssessment
Plugin for the LMS ILIAS open source to realize exams with writing of long texts.

The EDUTIEK project (acronym for "Einfache Durchführung textintensiver E-Klausuren") is developing a comprehensive software solution for online exams in subjects in which longer texts have to be submitted as exam solutions. These include law, history, linguistics, philosophy, sociology and many more.

The "Long Essay Assessment" is a repository object and bundles all functions for the realisation of a text exam. Responsibilities for creating, carrying out and correcting tasks are assigned to different people via the authorisation system. Support material can be provided for editing and correction is supported by an evaluation scheme. All results can be output in PDF/A format for documentation purposes.

The integrated "Writer" is a specialised editing page for examinees during the exam. The text editor and the task or additional material can be displayed side by side or on a full page. All editing steps are logged and are reversible. Even if the network is interrupted, you can continue writing and the editing steps will be saved afterwards. At the end of the editing time, the written text is displayed for review and its submission is finally confirmed.

The integrated "Corrector" is a specialised editing page for the proofreaders. In the submitted text, passages are marked and provided with comments. With each comment, partial points can be awarded based on the evaluation scheme. The text and comments are clearly displayed next to each other, optionally also with the comments from the first correction in the case of a second correction. To create the overall vote, a proposal for the final grade is calculated from the sum of the partial points, which can be accepted or changed. The vote can be used to create a textual overall assessment.

## System Requirements

The requirements of this plugin are nearly the same as for ILIAS 7 with the following exceptions:

* **PHP 7.4** is required. PHP 7.3 is not supported

* The following PHP extensions are required by the plugin: **curl, imagick, dom, json, xml, xsl**. On Debian/Ubuntu execute:

````
    apt-get install php7.4-curl, php7.4-dom, php7.4-imagick, php7.4-json, php7.4-xsl, php7.4-xsl
````
The PHP imagick extension uses Imagemagick and ghostscript to convert uploaded PDF files to images. On Debian/Ubuntu execute:

 ````
    apt-get install ghostscript
    apt-get install imagemagick
````

ImageMagick must be allowed to convert PDF files. To enable this please edit the file `/etc/ImageMagick-6/policy.xml` and 
ensure that the following line is ìncluded:

````
 <policy domain="coder" rights="read | write" pattern="PDF" />
````

## Installation

1. Copy the plugin to Customizing/global/plugins/Services/Repository/RepositoryObject
2. Go to the plugin folder
3. Execute ````composer install --no-dev````
4. Install the plugin in the ILIAS plugin administration

## Update from Git

1. Go to the plugin folder
2. Execute the following commands:
````
 rm -R vendor
 rm composer.lock
 composer install --no-dev
````

Please clear your browser cache after an update before you start the writing and correction screens.

## Branches and Versions

The plugin is published for ILIAS in different branches:

* **release1_ilias7** will receive bug fixes only
* **release2_ilias8** will be created by March 2024
* **main** is the current development branch. Please do not use it for production.

Versions 2.x will receive bug fixes as well as new features without breaking existing functionality and data.
Please consult the [CHANGELOG](CHANGELOG.md) to see the different versions.

## Known Issues

The writing and correction of exams is tested with Firefox and Chrome, so modern Chromium based browser should work. We know about issues with older Safari browsers. Please test with you local system before writing an exam and offer a tryout service for students who should write on their own device.