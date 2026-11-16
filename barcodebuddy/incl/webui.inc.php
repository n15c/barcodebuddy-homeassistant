<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


/**
 * functions for web ui
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 */


require_once __DIR__ . "/configProcessing.inc.php";
require_once __DIR__ . "/uiEditor.inc.php";
require_once __DIR__ . "/config.inc.php";

const MENU_GENERIC  = 0;
const MENU_MAIN     = 1;
const MENU_SETUP    = 2;
const MENU_SETTINGS = 3;
const MENU_ERROR    = 4;
const MENU_LOGIN    = 5;


class MenuItemLink {

    public $itemText;
    public $itemLink;
    public $itemId;

    function __construct() {
        $this->itemId = 'btn' . rand();
        return $this;
    }

    /**
     * @return static
     *
     * @param string $text
     */
    public function setText(string $text): self {
        $this->itemText = $text;
        return $this;
    }

    /**
     * @return static
     */
    public function setLink(string $link): self {
        $this->itemLink = $link;
        return $this;
    }

    /**
     * @return static
     *
     * @param string $id
     */
    public function setId(string $id): self {
        $this->itemId = $id;
        return $this;
    }
}


class WebUiGenerator {
    private $htmlOutput = "";
    private $menu = MENU_GENERIC;

    public function __construct(int $menu) {
        $this->menu = $menu;
    }


    public function addHtml(string $html): void {
        $this->htmlOutput = $this->htmlOutput . $html;
    }

    public function addScript(string $js): void {
        $this->htmlOutput = $this->htmlOutput . "<script>" . $js . "</script>";
    }


    public function printHtml(): void {
        echo $this->htmlOutput;
    }


    /**
     * @param string $title
     * @param string $html
     * @param MenuItemLink|null $links
     * @param string|null $id
     */
    public function addCard(string $title, string $html, ?MenuItemLink $links = null, ?string $id = null): void {
        if ($id == null) {
            $id = random_int(0, 9999999);
        }
        $this->addHtml('
        <section id="'.$id.'" class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4 class="bb-card-title">' . $title . '</h4>
        ' . $html . '
        </div>
            </div>');
        if ($links != null) {
            $linkArray = array();
            if (!is_array($links))
                $linkArray[0] = $links;
            else
                $linkArray = $links;

            $id = $linkArray[0]->itemId;
            $this->addHtml('<button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="' . $id . '">
                  <i class="material-icons">more_vert</i>
                </button>
                <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="' . $id . '">');

            foreach ($linkArray as $link) {
                $this->addHtml('<li class="mdl-menu__item" onclick="' . $link->itemLink . '">' . $link->itemText . '</li>');
            }
            $this->addHtml('</ul>');
        }
        $this->addHtml('</section>');
    }

    /**
     * Eingebettete Icons (Material Icons, Apache License 2.0) fuer Navigation und Kopfzeile.
     * Keine Schrift oder externe Datei noetig, funktioniert auch ohne Internet und ueber HTTP.
     */
    const SVG_ICONS = array(
        "logo"     => "M9.5 6.5v3h-3v-3h3M11 5H5v6h6V5zm-1.5 9.5v3h-3v-3h3M11 13H5v6h6v-6zm6.5-6.5v3h-3v-3h3M19 5h-6v6h6V5zm-6 8h1.5v1.5H13V13zm1.5 1.5H16V16h-1.5v-1.5zM16 13h1.5v1.5H16V13zm-3 3h1.5v1.5H13V16zm1.5 1.5H16V19h-1.5v-1.5zM16 16h1.5v1.5H16V16zm1.5-1.5H19V16h-1.5v-1.5zm0 3H19V19h-1.5v-1.5zM22 7h-2V4h-3V2h5v5zm0 15v-5h-2v3h-3v2h5zM2 22h5v-2H4v-3H2v5zM2 2v5h2V4h3V2H2z",
        "home"     => "M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z",
        "settings" => "M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z",
        "quantity" => "M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z",
        "chores"   => "M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z",
        "tag"      => "M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z",
        "api"      => "M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z",
        "admin"    => "M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z",
        "screen"   => "M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 1.99-.9 1.99-2L23 5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z",
        "external" => "M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z",
    );

    public static function svgIcon(string $name): string {
        return '<svg class="bb-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . self::SVG_ICONS[$name] . '"/></svg>';
    }

    /**
     * @param string|null $additionalHeader
     */
    public function addHeader(?string $additionalHeader = null, bool $enableDialogs = false): void {
        global $CONFIG;

        if ($this->menu == MENU_SETTINGS || $this->menu === MENU_GENERIC) {
            $folder = "../";
        } else {
            $folder = "./";
        }
        $hasMenu = ($this->menu != MENU_SETUP && $this->menu != MENU_ERROR && $this->menu != MENU_LOGIN);
        if ($this->menu == MENU_SETUP || $this->menu == MENU_ERROR) {
            $indexfile = "setup.php";
        } elseif ($this->menu == MENU_LOGIN) {
            $indexfile = "login.php";
        } else {
            $indexfile = "index.php";
        }
        $this->addHtml('<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
        <title>Barcode Buddy</title>

        <link rel="apple-touch-icon" sizes="57x57" href="' . $folder . 'incl/img/favicon/apple-icon-57x57.png">
        <link rel="apple-touch-icon" sizes="60x60" href="' . $folder . 'incl/img/favicon/apple-icon-60x60.png">
        <link rel="apple-touch-icon" sizes="72x72" href="' . $folder . 'incl/img/favicon/apple-icon-72x72.png">
        <link rel="apple-touch-icon" sizes="76x76" href="' . $folder . 'incl/img/favicon/apple-icon-76x76.png">
        <link rel="apple-touch-icon" sizes="114x114" href="' . $folder . 'incl/img/favicon/apple-icon-114x114.png">
        <link rel="apple-touch-icon" sizes="120x120" href="' . $folder . 'incl/img/favicon/apple-icon-120x120.png">
        <link rel="apple-touch-icon" sizes="144x144" href="' . $folder . 'incl/img/favicon/apple-icon-144x144.png">
        <link rel="apple-touch-icon" sizes="152x152" href="' . $folder . 'incl/img/favicon/apple-icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="' . $folder . 'incl/img/favicon/apple-icon-180x180.png">
        <link rel="icon" type="image/png" sizes="192x192"  href="' . $folder . 'incl/img/favicon/android-icon-192x192.png">
        <link rel="icon" type="image/png" sizes="32x32" href="' . $folder . 'incl/img/favicon/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="96x96" href="' . $folder . 'incl/img/favicon/favicon-96x96.png">
        <link rel="icon" type="image/png" sizes="16x16" href="' . $folder . 'incl/img/favicon/favicon-16x16.png">
        <meta name="msapplication-TileImage" content="' . $folder . 'incl/img/favicon/ms-icon-144x144.png">
        <meta name="msapplication-navbutton-color" content="#111827">
        <meta name="msapplication-TileColor" content="#111827">
        <meta name="apple-mobile-web-app-status-bar-style" content="#111827">
        <meta name="theme-color" content="#111827">


        <style>
        /* roboto-regular - vietnamese_latin-ext_latin_greek-ext_greek_cyrillic-ext_cyrillic */
        @font-face {
          font-family: "Roboto";
          font-style: normal;
          font-weight: 400;
          src: local(""),
               url(' . $folder . 'incl/fonts/roboto-v20-vietnamese_latin-ext_latin_greek-ext_greek_cyrillic-ext_cyrillic-regular.woff2) format("woff2"), /* Chrome 26+, Opera 23+, Firefox 39+ */
               url(' . $folder . 'incl/fonts/roboto-v20-vietnamese_latin-ext_latin_greek-ext_greek_cyrillic-ext_cyrillic-regular.woff) format("woff"); /* Chrome 6+, Firefox 3.6+, IE 9+, Safari 5.1+ */
        }
        </style>' . self::getIcons($folder) . '
        <link rel="stylesheet" href="' . $folder . 'incl/css/material.indigo-blue.min.css?v=' . BB_VERSION . '">
        <link rel="stylesheet" href="' . $folder . 'incl/css/main.css?v=' . BB_VERSION . '">
        <link rel="stylesheet" href="' . $folder . 'incl/css/theme.css?v=' . BB_VERSION . '"> ');
        if ($additionalHeader != null) {
            $this->addHtml($additionalHeader);
        }
        if ($enableDialogs) {
            $this->addHtml(' <link rel="stylesheet" href="' . $folder . 'incl/css/bootstrap.min.css?v=' . BB_VERSION . '">
                                 <script src="' . $folder . 'incl/js/jquery-3.5.1.slim.min.js?v=' . BB_VERSION . '"></script>
                                 <script src="' . $folder . 'incl/js/bootstrap.bundle.min.js?v=' . BB_VERSION . '"></script>
                                 <script src="' . $folder . 'incl/js/bootbox.min.js?v=' . BB_VERSION . '"></script>');
        }

        $this->addHtml('</head>

     <body class="mdl-demo mdl-base bb-body">
     <script src="' . $folder . 'incl/js/scripts_top.js?v=' . BB_VERSION . '"></script>

    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header' . ($hasMenu ? ' mdl-layout--fixed-drawer' : '') . ' bb-layout">
      <header class="mdl-layout__header bb-header">
        <div class="mdl-layout__header-row">
          <span class="mdl-layout-title bb-title"><a href="' . $folder . $indexfile . '">' . self::svgIcon("logo") . '<span>Barcode Buddy</span></a></span>
          <div class="mdl-layout-spacer"></div>');
        if ($hasMenu) {
            $this->addHtml('<nav class="bb-header-actions">');
            if (!$CONFIG->HIDE_LINK_SCREEN)
                $this->addHtml('<a class="bb-pill bb-pill--primary" href="' . $folder . 'screen.php">' . self::svgIcon("screen") . '<span>Screen</span></a>');
            if (!$CONFIG->HIDE_LINK_GROCY)
                $this->addHtml('<a class="bb-pill" target="_blank" rel="noopener" href="' . BBConfig::getInstance()["GROCY_BASE_URL"] . '">' . self::svgIcon("external") . '<span>Grocy</span></a>');
            $this->addHtml('</nav>');
        }
        $this->addHtml('  </div>
      </header>');
        if ($hasMenu) {
            $items = array(
                array("index.php", "Overview", "home"),
                array("menu/settings.php", "Settings", "settings"),
                array("menu/quantities.php", "Quantities", "quantity"),
                array("menu/chores.php", "Chores", "chores"),
                array("menu/tags.php", "Tags", "tag"),
                array("menu/apimanagement.php", "API", "api"),
            );
            if (!$CONFIG->DISABLE_AUTHENTICATION)
                $items[] = array("menu/admin.php", "Admin", "admin");
            $current = basename($_SERVER["PHP_SELF"] ?? "");
            $this->addHtml('<div class="mdl-layout__drawer bb-drawer">
        <span class="mdl-layout-title bb-drawer-title">' . self::svgIcon("logo") . '<span>Barcode Buddy</span></span>
        <nav class="mdl-navigation">');
            foreach ($items as $item) {
                $active = basename($item[0]) === $current ? " is-active" : "";
                $this->addHtml('<a class="mdl-navigation__link' . $active . '" href="' . $folder . $item[0] . '">'
                    . self::svgIcon($item[2]) . '<span>' . $item[1] . '</span></a>');
            }
            $this->addHtml('</nav>
        <div class="bb-drawer-footer">Version ' . BB_VERSION_READABLE . '</div>
      </div>');
        }
        $this->addHtml('<main class="mdl-layout__content" style="flex: 1 0 auto;">
      <div class="mdl-layout__tab-panel is-active" id="overview">');
    }

    public function addFooter(): void {
        global $CONFIG;

        if ($this->menu == MENU_SETTINGS || $this->menu === MENU_GENERIC) {
            $folder = "../";
        } else {
            $folder = "./";
        }

        $this->addHtml(' <section class="section--footer mdl-grid">
          </section>
<div id="snackbar" class="mdl-js-snackbar mdl-snackbar">
  <div class="mdl-snackbar__text"></div>
  <button class="mdl-snackbar__action" type="button"></button>
</div>


<footer class="mdl-mini-footer bb-footer">
      <div class="mdl-mini-footer__left-section">
        <ul class="mdl-mini-footer__link-list">
              <li><a href="https://barcodebuddy-documentation.readthedocs.io/en/latest/">Documentation</a></li>
              <li><a href="https://github.com/n15c/barcodebuddy-homeassistant/tree/master/barcodebuddy">Source Code (Home Assistant Add-on)</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/">Original</a></li>
              <li><a href="https://github.com/n15c/barcodebuddy-homeassistant/blob/master/barcodebuddy/LICENSE.md">License (AGPL-3.0)</a></li>
          <li>Version ' . BB_VERSION_READABLE . '</li>
              <li>by Marc Ole Bulling</li>
        </ul>
      </div>
    </footer>
          </div></main>');

        if ($this->menu == MENU_MAIN) {
            $this->addHtml('<div id="myModal" class="modalmain">

          <!-- Modal content -->
          <div class="modalmain-content">
            <span class="close">&times;</span>
            <div>
            <h2>Add barcode</h2>

        Enter your barcodes below, one each line.&nbsp;<br><br>
        <form name="form" onsubmit="disableSSE()" method="post" action="' . $CONFIG->getPhpSelfWithBaseUrl() . '" >
        <textarea name="newbarcodes" id="newbarcodes" class="mdl-textfield__input" rows="15"></textarea>

        <br>

        <button  class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast" name="button_add_manual"  id="button_add_manual" type="submit" value="Add">Add</button>​

        <br><br>

        <span style="font-size: 9px;">It is recommended to use a script that grabs the barcode scanner input, instead of doing it manually. See the <a href="https://barcodebuddy-documentation.readthedocs.io/en/latest/usage.html#adding-barcodes-automatically" rel="noopener noreferrer" target="_blank">documentation</a> on how to do this.</span><br>

        </form>
          </div>
          </div>
        </div>
         <button id="add-barcode" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Add barcode</button> ');
        }
        if ($this->menu == MENU_SETTINGS) {
            $this->addHtml('<button id="save-settings" onclick="checkAndReturn()" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Save</button>');
        }
        $this->addHtml('</div><script src="' . $folder . 'incl/js/material.min.js?v=' . BB_VERSION . '"></script>' .
            '<script src="' . $folder . 'incl/js/scripts.js?v=' . BB_VERSION . '"></script>');

        if ($this->menu == MENU_MAIN) {
            $this->addScript('
        var eventSource = null;

        function disableSSE() {
            if (eventSource!=null)
                eventSource.close();
        }

        var modal = document.getElementById("myModal");
        var btn = document.getElementById("add-barcode");
        var span = document.getElementsByClassName("close")[0];
        btn.onclick = function() {
          modal.style.display = "block";
          btn.style.display = "none";
        document.getElementById("newbarcodes").focus();
        }
        span.onclick = function() {
          modal.style.display = "none";
          btn.style.display = "block";
        }
        window.onclick = function(event) {
          if (event.target == modal) {
            modal.style.display = "none";
            btn.style.display = "block";
          }
        }

        const delay = ms => new Promise(res => setTimeout(res, ms));

        const startWebsocket = async () => {
            /* waiting 1s in case barcode was added from ui */
        await delay(1000);

        eventSource = new EventSource("incl/sse/sse_data.php");
        var connectFailCounter = 0;
        eventSource.addEventListener("error", function (event) {
            if (event.target.readyState === EventSource.CONNECTING) {
                    connectFailCounter++
                    if (connectFailCounter === 100) {
                        eventSource.close();
                    }
            }
        }, false);
        eventSource.onmessage = function(event) {
            var resultJson = JSON.parse(event.data);
                var resultCode = resultJson.data.substring(0, 1);
                var resultText = resultJson.data.substring(1);  
                switch(resultCode) {
                    case \'0\':
                    case \'1\':
                    case \'2\':

                    var xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                      if (this.readyState == 4 && this.status == 200) {
                          var content = JSON.parse(this.responseText);
                          var card1 = document.getElementById("f1");
                          card1.innerHTML = content.f1;
                          var card2 = document.getElementById("f2");
                          card2.innerHTML = content.f2;
                          var card3 = document.getElementById("f3");
                          card3.innerHTML = content.f3;
                          if (content.f4 != "null")
                            location.reload();
                      }
                    };
                    xhttp.open("GET", "index.php?ajaxrefresh", true);
                    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhttp.send();
                        break;
                      }
                  };
                };
                if(typeof(EventSource) !== "undefined")
                  startWebsocket();');
        }
        $this->addHtml('</body>
    </html>');
    }


    public function addAlert(string $text, ?string $title = null, ?string $callback = null, string $size = "medium"): void {
        $alert = 'bootbox.alert({
                message: "' . $text . '",
                size: "' . $size . '",';
        if ($callback != null)
            $alert .= "\ncallback: function(){" . $callback . "},";
        if ($title != null)
            $alert .= "\ntitle: '" . $title . "',";
        $alert .= '});';
        $this->addScript($alert);
    }


    public function addConfirmDialog(string $text, string $callback, ?string $title = null, string $buttonPositive = "Confirm",
                                     string $buttonNegative = "Cancel", string $size = "medium"): void {
        $alert = 'bootbox.confirm({
                message: "' . $text . '",
                size: "' . $size . '",
                buttons: {
                    cancel: {
                        label: "' . $buttonNegative . '",
                        className: "btn-secondary"
                    },
                    confirm: {
                        label: "' . $buttonPositive . '",
                        className: "btn-success"
                    }
                },
                callback: function (result) {' . $callback . '},';
        if ($callback != null)
            $alert .= "\n";
        if ($title != null)
            $alert .= "\ntitle: '" . $title . "',";
        $alert .= '});';
        $this->addScript($alert);
    }

    private static function getIcons(string $folder): string {
        return "<style>
        
        
        @font-face {
          font-family: \"Material Icons\";
          font-style: normal;
          font-weight: 400;
          src: url(" . $folder . "incl/fonts/materialicons.woff2) format(\"woff2\");
        }
        
        .material-icons {
          font-family: \"Material Icons\";
          font-weight: normal;
          font-style: normal;
          font-size: 24px;
          line-height: 1;
          letter-spacing: normal;
          text-transform: none;
          display: inline-block;
          white-space: nowrap;
          speak: never;
          word-wrap: normal;
          direction: ltr;
          -webkit-font-feature-settings: \"liga\";
          -webkit-font-smoothing: antialiased;
        }
        
        
        .material-icons-small {
          font-family: \"Material Icons\";
          display: inline-block;
          font: normal normal normal 14px/1 \"Material Icons\";
          font-size: inherit;
          text-rendering: auto; 
           font-weight: normal;
          font-style: normal;
          -webkit-font-smoothing: antialiased;
          -moz-osx-font-smoothing: grayscale;
        }
        
        @font-face {
          font-family: 'icomoon';
          src:  url('" . $folder . "incl/fonts/icomoon.woff') format('woff');
          font-weight: normal;
          font-style: normal;
          font-display: block;
        }

        [class^=\"icon-\"], [class*=\" icon-\"] {
          /* use !important to prevent issues with browser extensions that change fonts */
          font-family: 'icomoon' !important;
          speak: never;
          font-style: normal;
          font-weight: normal;
          font-variant: normal;
          text-transform: none;
          line-height: 1;
        
          /* Better Font Rendering =========== */
          -webkit-font-smoothing: antialiased;
          -moz-osx-font-smoothing: grayscale;
        }
        
        .icon-navigation-more:before {
          content: \"\\e900\";
        }
        .icon-cog:before {
          content: \"\\e994\";
        }
        .icon-bin:before {
          content: \"\\e9ac\";
        }
        .icon-flag:before {
          content: \"\\e9cc\";
        }
        .icon-notification:before {
          content: \"\\ea08\";
        }
        .icon-plus:before {
          content: \"\\ea0a\";
        }
        .icon-checkmark:before {
          content: \"\\ea10\";
        }
        </style>";
    }
}

class TableGenerator {
    private $htmlOutput = "";

    function __construct(array $tableHeadItems) {
        $this->htmlOutput = '<table class="mdl-data-table mdl-js-data-table mdl-cell">
                 <thead>
                    <tr>';
        foreach ($tableHeadItems as $item) {
            $this->htmlOutput = $this->htmlOutput . '<th class="mdl-data-table__cell--non-numeric">' . $item . '</th>';
        }
        $this->htmlOutput = $this->htmlOutput . '    </tr>
                  </thead>
                  <tbody>';
    }


    function startRow(): void {
        $this->htmlOutput = $this->htmlOutput . '<tr>';
    }

    function addCell(string $html): void {
        $this->htmlOutput = $this->htmlOutput . '<td class="mdl-data-table__cell--non-numeric">' . $html . '</td>';
    }

    function endRow(): void {
        $this->htmlOutput = $this->htmlOutput . '</tr>';
    }

    function getHtml(): string {
        return $this->htmlOutput . '</tbody></table>';
    }

}


/**
 * @return never
 */
function hideGetPostParameters() {
    global $CONFIG;
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}
