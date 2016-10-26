AndroidGeneratorBundle
======================

This bundle provides a command to generate Android application and needed RESTApi based on your bundle definition.

How it works
------------

A generator prompt some information: AppName, package, destination folder, ...
After generate Android application based on YourBundle in destination folder :

- Entity's and ContentProvider's based on YourBundle\Entity class.
- Account based on FOSUser (YourBundle\Entity\User).
- SyncService and SyncAdapter between Account and Provider's.

Base application generated contain some additional class:

- Api class for authentication and communication with Api.
- Authenticator system (include Account login activity and Account service).
- Helper's for Account, Database and Entity
- Various utilities class.
- And a Constant class (root package) that containing features like DB version, API url, ...

Installation
------------

_Android SDK must be installed !!_

Add repository to your composer.json

``` JSON 
...
"repositories" : [{
    "type" : "vcs",
    "url" : "https://github.com/btouchard/AndroidGeneratorBundle.git"
}],
...
```

Next install Bundle using [composer](https://getcomposer.org/doc/00-intro.md) 
``` BASH
php composer.phar require kolapsis/androidgenerator-bundle
```

Usage
-----

- Create your FOSUser class and configure FOSUser
- Create your's Entities in YourBundle\Entity with AndroidAnnotation
- Create your API and configure firewall (being built generator)
- Generate your Android Application with:

``` BASH
php bin/console android:create:app YourBundle
```

Documentation
-------------

Coming soon
<!--[Read the documentation](http://androidgeneratorbundle.kolapsis.com)-->

License
-------

This bundle is under the MIT license. See the complete license in the bundle:

    Resources/meta/LICENSE