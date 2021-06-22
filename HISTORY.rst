===============
Version History
===============


1.0.0 - 22.06.2021
------------------

This is the initial version which establishes the core functionality of the plugin. The core functionality is
considered to consist of the following features:

- Vue frontend: The frontend user interaction of all plugin functionality is managed using various vue frontend
  components. The frontend code provided multiple different components which will be displayed in different contexts
  such as the meta boxes for the custom post types. These components are dynamically mounted to html components with
  specific ids, which in turn are provided by the backend.
- The log system: The "log" custom post type is a supporting post type. The wrapper class LogPost.php can be used like
  a log object for the server code. The log messages can be viewed on the edit page of this post type through a
  custom vue component within an additional meta box.
- The command system: The command system can be used to execute lengthy / expensive background commands on the server.
  the execution process of these commands is recorded within the log posts and can later be reviewed by the user. To
  interact / trigger these commands, a custom dashboard widget is provided which allows to select the command, enter
  parameters and start the background execution.
- "observed author" custom post type. This custom post type represents observed author. Observed authors are those
  whose publications are generally considered important for the wordpress site. Consists of personal information,
  scopus author ids, optionally scopus affiliation blacklist
- "publication" custom post type. Represents the actual publications. This is also the public post type which can then
  later be incorporated into the actual site, displaying to the users which publications have recently been produced
  by the various observed authors. Contains informations about various ids such as DOI, ISSN and title, abstract
  authors, journal etc.
- import scopus command: This background command can be executed to automatically import new publications based on the
  defined observed authors from the scopus database.

Feature ideas
-------------

- import from different publication databases
    - import from KITOpen
- More options to control the behavior
- Custom import filters