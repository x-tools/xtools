<?php

/* default/index.html.twig */
class __TwigTemplate_5c85aa042f35ef9efacc9d5c92621c046a58ef3ec85ac086be3e2fe8ac5bd640 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("base.html.twig", "default/index.html.twig", 1);
        $this->blocks = array(
            'title' => array($this, 'block_title'),
            'body' => array($this, 'block_body'),
            'stylesheetsOld' => array($this, 'block_stylesheetsOld'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "base.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_60497f51812c744d1a22cc26701a05ec7c7f7f977aefad8f689256cb0bdfd6ba = $this->env->getExtension("native_profiler");
        $__internal_60497f51812c744d1a22cc26701a05ec7c7f7f977aefad8f689256cb0bdfd6ba->enter($__internal_60497f51812c744d1a22cc26701a05ec7c7f7f977aefad8f689256cb0bdfd6ba_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "default/index.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_60497f51812c744d1a22cc26701a05ec7c7f7f977aefad8f689256cb0bdfd6ba->leave($__internal_60497f51812c744d1a22cc26701a05ec7c7f7f977aefad8f689256cb0bdfd6ba_prof);

    }

    // line 3
    public function block_title($context, array $blocks = array())
    {
        $__internal_b768b2292628ff8458b2b044ecedaafefd326c5fab98d2c30582e6e948394bdb = $this->env->getExtension("native_profiler");
        $__internal_b768b2292628ff8458b2b044ecedaafefd326c5fab98d2c30582e6e948394bdb->enter($__internal_b768b2292628ff8458b2b044ecedaafefd326c5fab98d2c30582e6e948394bdb_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        echo "Welcome to x!'s tools!";
        
        $__internal_b768b2292628ff8458b2b044ecedaafefd326c5fab98d2c30582e6e948394bdb->leave($__internal_b768b2292628ff8458b2b044ecedaafefd326c5fab98d2c30582e6e948394bdb_prof);

    }

    // line 5
    public function block_body($context, array $blocks = array())
    {
        $__internal_31742376dea197a85b1e7a47da75dfc9581534e9ea47ce29aac0b15cc6015346 = $this->env->getExtension("native_profiler");
        $__internal_31742376dea197a85b1e7a47da75dfc9581534e9ea47ce29aac0b15cc6015346->enter($__internal_31742376dea197a85b1e7a47da75dfc9581534e9ea47ce29aac0b15cc6015346_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 6
        echo "    <div id=\"wrapper\">
        <div id=\"container\">
            <div id=\"welcome\">
                <h1><span>Welcome to</span> xTools</h1>
            </div>

        </div>
    </div>
";
        
        $__internal_31742376dea197a85b1e7a47da75dfc9581534e9ea47ce29aac0b15cc6015346->leave($__internal_31742376dea197a85b1e7a47da75dfc9581534e9ea47ce29aac0b15cc6015346_prof);

    }

    // line 17
    public function block_stylesheetsOld($context, array $blocks = array())
    {
        $__internal_bc2b6498ce3bde5e4890093b594cd18354a5f11064a5dce64a3ccaac85119103 = $this->env->getExtension("native_profiler");
        $__internal_bc2b6498ce3bde5e4890093b594cd18354a5f11064a5dce64a3ccaac85119103->enter($__internal_bc2b6498ce3bde5e4890093b594cd18354a5f11064a5dce64a3ccaac85119103_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "stylesheetsOld"));

        // line 18
        echo "    <style>
        body { background: #F5F5F5; font: 18px/1.5 sans-serif; }
        h1, h2 { line-height: 1.2; margin: 0 0 .5em; }
        h1 { font-size: 36px; }
        h2 { font-size: 21px; margin-bottom: 1em; }
        p { margin: 0 0 1em 0; }
        a { color: #0000F0; }
        a:hover { text-decoration: none; }
        code { background: #F5F5F5; max-width: 100px; padding: 2px 6px; word-wrap: break-word; }
        #wrapper { background: #FFF; margin: 1em auto; max-width: 800px; width: 95%; }
        #container { padding: 2em; }
        #welcome, #status { margin-bottom: 2em; }
        #welcome h1 span { display: block; font-size: 75%; }
        #icon-status, #icon-book { float: left; height: 64px; margin-right: 1em; margin-top: -4px; width: 64px; }
        #icon-book { display: none; }

        @media (min-width: 768px) {
            #wrapper { width: 80%; margin: 2em auto; }
            #icon-book { display: inline-block; }
            #status a, #next a { display: block; }

            @-webkit-keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }
            @keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }
            .sf-toolbar { opacity: 0; -webkit-animation: fade-in 1s .2s forwards; animation: fade-in 1s .2s forwards;}
        }
    </style>
";
        
        $__internal_bc2b6498ce3bde5e4890093b594cd18354a5f11064a5dce64a3ccaac85119103->leave($__internal_bc2b6498ce3bde5e4890093b594cd18354a5f11064a5dce64a3ccaac85119103_prof);

    }

    public function getTemplateName()
    {
        return "default/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  75 => 18,  69 => 17,  54 => 6,  48 => 5,  36 => 3,  11 => 1,);
    }
}
/* {% extends 'base.html.twig' %}*/
/* */
/* {% block title %}Welcome to x!'s tools!{% endblock %}*/
/* */
/* {% block body %}*/
/*     <div id="wrapper">*/
/*         <div id="container">*/
/*             <div id="welcome">*/
/*                 <h1><span>Welcome to</span> xTools</h1>*/
/*             </div>*/
/* */
/*         </div>*/
/*     </div>*/
/* {% endblock %}*/
/* */
/* */
/* {% block stylesheetsOld %}*/
/*     <style>*/
/*         body { background: #F5F5F5; font: 18px/1.5 sans-serif; }*/
/*         h1, h2 { line-height: 1.2; margin: 0 0 .5em; }*/
/*         h1 { font-size: 36px; }*/
/*         h2 { font-size: 21px; margin-bottom: 1em; }*/
/*         p { margin: 0 0 1em 0; }*/
/*         a { color: #0000F0; }*/
/*         a:hover { text-decoration: none; }*/
/*         code { background: #F5F5F5; max-width: 100px; padding: 2px 6px; word-wrap: break-word; }*/
/*         #wrapper { background: #FFF; margin: 1em auto; max-width: 800px; width: 95%; }*/
/*         #container { padding: 2em; }*/
/*         #welcome, #status { margin-bottom: 2em; }*/
/*         #welcome h1 span { display: block; font-size: 75%; }*/
/*         #icon-status, #icon-book { float: left; height: 64px; margin-right: 1em; margin-top: -4px; width: 64px; }*/
/*         #icon-book { display: none; }*/
/* */
/*         @media (min-width: 768px) {*/
/*             #wrapper { width: 80%; margin: 2em auto; }*/
/*             #icon-book { display: inline-block; }*/
/*             #status a, #next a { display: block; }*/
/* */
/*             @-webkit-keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }*/
/*             @keyframes fade-in { 0% { opacity: 0; } 100% { opacity: 1; } }*/
/*             .sf-toolbar { opacity: 0; -webkit-animation: fade-in 1s .2s forwards; animation: fade-in 1s .2s forwards;}*/
/*         }*/
/*     </style>*/
/* {% endblock %}*/
/* */
