*********************
Development of xTools
*********************

To contribute to the development of xTools, you may fork us on GitHub.  A few things to be aware of first:

1. xTools is based on Symfony 3.0. We use Twig as our templating engine.  Symfony is a full MVC system.
  a. The controllers are located at ``src/AppBundle/controller``.  They are sorted by tool
  b. The twig templates are located at ``app/resources/views``.  They are sorted by tool.
2. We use the ``@Route`` syntax to configure routes. 
3. Every tool requires a twig directory and one controller. Also, core parts of xTools require the tool to be registered within `app/config/tools.yml`.

Style Guideline
---------------
- We use spaces to indent code.  4 spaces per "tab"
- Opening and closing curly braces must be on their own lines.
- Variable names are camelCase.  Constants are ALL CAPS.  Function names are camelCase.

Running Development server
--------------------------
Follow these steps

1. Download the repository.
2. Run ``composer install``
3. Issue ``php bin/console server:run``.
4. Visit ``http://localhost:8000`` in your web browser.

The development server does not cache data.  Any changes you make are visible after refreshing the page.

Additional Help
---------------
Please contact `User:Matthewrbowker <https://en.wikipedia.org/wiki/User:Matthewrbowker>`_ or `User:MusikAnimal <https://en.wikipedia.org/wiki/User:MusikAnimal>`_ if you need help.  Or, you are welcome to visit us on `IRC <https://webchat.freenode.net/?channels=#wikimedia-xtools>`_ (`Direct link <irc://irc.freenode.net/#wikimedia-xtools>`_ - Requires an IRC client).
