## MooPT architecture

```
+----------------------------------------------------------------------------+
|                                                                            |
|                               +------------------------------------------+ |
|  qtype_moopt                  |                                          | |
|                               |   Code to integrate ACE into MooPT       | |
|                               |   (taken from Coderunner Moodle Plugin)  | |
|                               |                                          | |
|                               |   Source files:                          | |
|                               |   amd/src/textareas.js                   | |
|                               |   amd/src/ui_ace.js                      | |
|                               |   amd/src/userinterfacewrapper.js        | |
|                               |                                          | |
|                               +------------------------------------------+ |
|                                                                            |
|                               +------------------------+                   |
|                               |                        |                   |
|                               |   ACE Web-Editor       |                   |
|                               |   (https://ace.c9.io/) |                   |
|                               |                        |                   |
|                               |   Source files:        |                   |
|                               |   ace/*                |                   |
|                               |                        |                   |
|                               +------------------------+                   |
|                                                                            |
+----------------------------------------------------------------------------+
```

----------

## AMD Modules

The JavaScript files below **amd/src/** must be minifed to **amd/build/** after changes of the source files in **amd/src/**, because Moodle will use the minified files rather than the normal source files. During development of javascript source files we should set the option "cache javascript" in Moodle to off so the browser interprets additional source map files for mapping the minified source to the original one. 

[Since version 3.8 Moodle](https://docs.moodle.org/dev/Javascript_Modules#Development_mode_.28Moodle_v3.8_and_above.29) won't deliver the original source files below **amd/src/** to the browser, so minification to **amd/build/** is a must.

For minification of the AMD Modules, Moodle is using grunt.


### HowTo Grunt
This section is based on the following two pages:   
 - https://docs.moodle.org/dev/Grunt  
 - https://docs.moodle.org/dev/Javascript_Modules#Install_grunt  

This section does also only explain the minification based on a Windows system, so on other systems it could be different.

#### Installation of Grunt
At first you need to install Node.js on your system (https://nodejs.org/). The Node.js version that is supported by Moodle is [documented here](https://docs.moodle.org/dev/Javascript_Modules#Install_NVM_and_Node). In the Windows installer check "Automatically install the necessary tools".

If you missed the automatic installation of necessary tools, after installation you can run "Install additional tools for Node.js" from the Windows start menu.

Then as administrator open CMD and navigate to the directory in which Moodle is installed.
From there execute the following two commands:  
```npm install```  
```npm install -g grunt-cli```  
It may happen that vulnerabilities are mentioned, you can ignore that.


#### Running Grunt

Use CMD and move into the AMD directory of the plugin and run the following command:   
```grunt amd```  

It can happen that this fails because this command also checks the code with the ESLint Code Analysis Tool and when it finds "problematic patterns" it will return some errors. 

When these "problematic patterns" in the code are no real problems you can also run:   
```grunt amd --force```   
instead to minify the files even when the Code Analysis Tool finds problematic code.

After that the minified files should be under amd/build/.

#### Running Grunt automatically on changes

You might have to install watchman first. Therefore as an administrator start the Node.js command prompt via the Windows start menu and type:
```choco install watchman```

Then at a CMD prompt move into the AMD directory of the plugin and run the following command:   
```grunt --verbose watch```  

In order to have the force option on when running the task eslint via watchman, which could be helpful during development, you can modify the `Gruntfile.js` inside the moodle main directory as follows:

```
// Register JS tasks.
grunt.registerTask('shifter', 'Run Shifter against the current directory', tasks.shifter);
grunt.registerTask('gherkinlint', 'Run gherkinlint against the current directory', tasks.gherkinlint);
grunt.registerTask('ignorefiles', 'Generate ignore files for linters', tasks.ignorefiles);
grunt.registerTask('watch', 'Run tasks on file changes', tasks.watch);
grunt.registerTask('yui', ['eslint:yui', 'shifter']);
// BEGIN mod
grunt.loadNpmTasks('grunt-force-task');
grunt.registerTask('amd', ['force:eslint:amd', 'babel']);
// END mod
grunt.registerTask('js', ['amd', 'yui']);
```

For this to work you might have to install this first:
```npm install grunt-force-task --save-dev```

Then restart `grunt --verbose watch`.

----------

## Ace WebEditor

MooPT is currently using the Ace WebEditor Version 1.4.8 (https://ace.c9.io/).  

- The files of the Ace Editor are located under **ace/**  
- The integration of Ace is done by three javascript AMD Modules that have been copied from Coderunner Version 3.7.5 (https://moodle.org/plugins/qtype_coderunner) and slightly tweaked to fit MooPT:
  * The three javascript files are: **textareas.js**, **ui_ace.js** and **userinterfacewrapper.js** in **amd/src/**.
  * The corresponding minified files are in **amd/build/**