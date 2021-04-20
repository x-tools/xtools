Thanks for contributing!
========================

If you need help setting up XTools on your local machine, refer to the documentation:
* https://xtools.readthedocs.io/en/stable/installation.html
* https://xtools.readthedocs.io/en/stable/configuration.html
* https://xtools.readthedocs.io/en/stable/development.html

If you run into any trouble, ask for help at https://www.mediawiki.org/wiki/Talk:XTools or on IRC in
the #wikimedia-xtools channel on freenode ([live chat](https://webchat.freenode.net/?channels=#wikimedia-xtools)).

For pull requests
-----------------

Please:

* The first line of the commit message should be prefixed with the relevant tool name, where applicable.
  For example `EditCounter: count abuse filter changes`. Other tool names include `ArticleInfo`,
  `Pages`, `TopEdits`, `AutoEdits`, `CategoryEdits`, `AdminStats`, `AdminScore`, `EditSummary` and `SimpleCounter`.
* If there is a [Phabricator task](https://phabricator.wikimedia.org/tag/xtools/) associated with the commit,
  the last line of the commit message should be formatted like `Bug: T123` where `T123` is the task number.
  A link to the commit will then show up automatically on the task.
