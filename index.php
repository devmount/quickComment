<?php

/**
 * moziloCMS Plugin: quickComment
 *
 * Makes pages commentable
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_MoziloPlugins
 * @author   DEVMOUNT <mail@devmount.de>
 * @license  GPL v3+
 * @version  GIT: v1.1.2013-09-19
 * @link     https://github.com/devmount/quickComment
 * @link     http://devmount.de/Develop/moziloCMS/Plugins/quickComment.html
 * @see      I can do all this through him who gives me strength.
 *           – The Bible
 *
 * Plugin created by DEVMOUNT
 * www.devmount.de
 *
 */

// only allow moziloCMS environment
if (!defined('IS_CMS')) {
    die();
}

/**
 * quickComment Class
 *
 * @category PHP
 * @package  PHP_MoziloPlugins
 * @author   DEVMOUNT <mail@devmount.de>
 * @license  GPL v3+
 * @link     https://github.com/devmount/quickComment
 */
class quickComment extends Plugin
{
    // language
    private $_admin_lang;
    private $_cms_lang;

    // plugin information
    const PLUGIN_AUTHOR  = 'DEVMOUNT';
    const PLUGIN_TITLE   = 'quickComment';
    const PLUGIN_VERSION = 'v1.1.2013-09-19';
    const MOZILO_VERSION = '2.0';
    const PLUGIN_DOCU
        = 'http://devmount.de/Develop/moziloCMS/Plugins/quickComment.html';

    private $_plugin_tags = array(
        'tag1' => '{quickComment|<name>}',
    );

    const LOGO_URL = 'http://media.devmount.de/logo_pluginconf.png';

    /**
     * set configuration elements, their default values and their configuration
     * parameters
     *
     * @var array $_confdefault
     *      text     => default, type, maxlength, size, regex
     *      textarea => default, type, cols, rows, regex
     *      password => default, type, maxlength, size, regex, saveasmd5
     *      check    => default, type
     *      radio    => default, type, descriptions
     *      select   => default, type, descriptions, multiselect
     */
    private $_confdefault = array(
        'spamschutz' => array(
            true,
            'check',
        ),
        'zeichenbegrenzung' => array(
            true,
            'check',
        ),
        'zeichenanzahl' => array(
            '300',
            'text',
            '100',
            '5',
            "/^[0-9]{2,6}$/",
        ),
        'breaksincomments' => array(
            true,
            'check',
        ),
        'date' => array(
            'd.m.y',
            'text',
            '100',
            '5',
            "/^[0-9]{1,3}$/",
        ),
        'time' => array(
            'H:i',
            'text',
            '100',
            '5',
            "/^[0-9]{1,3}$/",
        ),
        'reloadsperre' => array(
            '600',
            'text',
            '100',
            '5',
            "/^[0-9]{0,4}$/",
        ),
        'datumtrenner' => array(
            ' ',
            'text',
            '100',
            '5',
            "/^[^A-Za-z0-9]{1,8}$/",
        ),
        'sortierung' => array(
            'red',
            'radio',
            array('neueoben', 'neueunten'),
        ),
        'formtemplate' => array(
            '{MESSAGE}{NAME}{COMMENT}{CHARLIMIT}{SPAM}{SUBMIT}',
            'textarea',
            '10',
            '10',
            "/^[a-zA-Z0-9]{1,10}$/",
        ),
        'formanordnung' => array(
            'red',
            'radio',
            array('oben', 'unten'),
        ),
        'mail' => array(
            '',
            'text',
            '100',
            '5',
            "/^[\w-]+(\.[\w-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\.)+([a-z]{2,4})$/i",
        ),
        'adminuser' => array(
            '',
            'text',
            '100',
            '5',
            "/^[0-9]{1,3}$/",
        ),
    );

    /**
     * creates plugin content
     *
     * @param string $value Parameter divided by '|'
     *
     * @return string HTML output
     */
    function getContent($value)
    {
        global $CMS_CONF;
        global $CatPage;

        // initialize cms lang
        $this->_cms_lang = new Language(
            $this->PLUGIN_SELF_DIR
            . 'lang/cms_language_'
            . $CMS_CONF->get('cmslanguage')
            . '.txt'
        );

        // get conf and set default
        $conf = array();
        foreach ($this->_confdefault as $elem => $default) {
            $conf[$elem] = ($this->settings->get($elem) == '')
                ? $default[0]
                : $this->settings->get($elem);
        }

        // build filename used for $value specific database
        $file = 'qc_'.$value.'.txt';
        $filename = 'plugins/quickComment/'.$file;

        // Formulardaten einlesen
        $get_schreiber = '';
        $get_text = '';
        $get_number = '';
        $get_arithmetic = '';
        $is_send = getRequestValue('submit', false);
        if (isset($_SESSION['commentform_schreiber'])) {
            $get_schreiber
                = trim(getRequestValue($_SESSION['commentform_schreiber'], false));
        }
        if (isset($_SESSION['commentform_text'])) {
            $get_text
                = trim(getRequestValue($_SESSION['commentform_text'], false));
        }
        if (isset($_SESSION['commentform_number'])) {
            $get_number
                = trim(getRequestValue($_SESSION['commentform_number'], false));
        }
        if (isset($_SESSION['commentform_arithmetic'])) {
            $get_arithmetic
                = trim(getRequestValue($_SESSION['commentform_arithmetic'], false));
        }

        // Delete-Formular Daten einlesen
        $get_delete_id = getRequestValue('delete_id', false);
        $delete_request_send = getRequestValue('delete_submit', false);

        // Kodierung handlen
        $get_schreiber = urldecode($get_schreiber);
        $get_text = urldecode($get_text);

        // check number of characters
        if (
            $conf['zeichenbegrenzung']
            and mb_strlen($get_text) > $conf['zeichenanzahl']+30
        ) {
            $get_text = substr($get_text, 0, $conf['zeichenanzahl']+30);
        }

        // Nachricht und Nachrichttypen initialisieren
        $info = $this->_cms_lang->getLanguageValue('qc_message_allefelder');
        $message_error = false;
        $message_succes = false;

        // Formular wurde abgesendet
        if ($is_send <> '') {
            // Reloadsperre
            if (time() - $_SESSION['commentform_load'] < $conf['reloadsperre']) {
                $info = $this->_cms_lang->getLanguageValue(
                    'qc_message_reload',
                    $conf['reloadsperre']
                );
                $message_error = true;
            }
            // Fehlende Formulareingaben handlen
            else if (trim($get_text) == '' && trim($get_schreiber) == '') {
                $info = $this->_cms_lang->getLanguageValue('qc_message_allefelder');
                $message_error = true;
            }
            else if (trim($get_text) == '') {
                $info = $this->_cms_lang->getLanguageValue('qc_message_keinkommentar');
                $message_error = true;
            }
            else if (trim($get_schreiber) == '') {
                $info = $this->_cms_lang->getLanguageValue('qc_message_keinname');
                $message_error = true;
            }
            else if (trim($get_text) != '' && trim($get_schreiber) != '') {
                // Spamschutzüberprüfung
                if ($conf['spamschutz'] == 'true' && $get_number != md5($get_arithmetic)) {
                    $info = $this->_cms_lang->getLanguageValue('qc_message_spamfalsch');
                    $message_error = true;
                }
                // Alles in Ordnung
                else {
                    // Datei schreiben
                    $datei = fopen($filename,'a+') or die($this->_cms_lang->getLanguageValue('qc_message_fehler'));
                    if ($datei < 0 ) {
                        $info = $this->_cms_lang->getLanguageValue('qc_message_nichtgesendet');
                        $message_error = true;
                    }
                    #$date1 = date($conf['date']);
                    #$date2 = date($conf['time']);
                    $tstamp = time();

                    // Zeilenumbrüche in der Textarea je nach conf handlen
                    if ($conf['breaksincomments'] == 'true') {
                        $get_text = str_replace("\n",'<br />',$get_text);
                    } else {
                        $get_text = str_replace("\n",' ',$get_text);
                    }
                    $get_text = str_replace("\r",'',$get_text);

                    $inhalt = sprintf("%s§%s§%s\n",str_replace('§','&sect;',$get_schreiber),$tstamp,str_replace('§','&sect;',$get_text));
                    fwrite($datei, $inhalt);
                    fclose($datei);
                    // chown($filename, 2545);

                    // falls gesetzt, Bestätigungsmail senden
                    if ($conf['mail'] <> '') {
                        $mailsubject = $this->_cms_lang->getLanguageValue(
                            'qc_mail_subject',
                            $CMS_CONF->get('websitetitle'),
                            $CatPage->get_HrefText(CAT_REQUEST,PAGE_REQUEST));
                        $mailcontent = $this->_cms_lang->getLanguageValue(
                            'qc_mail_content_head',
                            utf8_decode($get_schreiber),
                            date(
                                $conf['date'] . $conf['datumtrenner'] . $conf['time'],
                                $tstamp
                            ) . "\r\n"
                        );
                        $mailcontent .= utf8_decode($get_text) . "\r\n" . $this->_cms_lang->getLanguageValue('qc_mail_content_text', $file, 'www.'.$_SERVER['SERVER_NAME'].$CatPage->get_Href(CAT_REQUEST,PAGE_REQUEST));
                        require_once BASE_DIR_CMS.'Mail.php';
                        sendMail($mailsubject, $mailcontent, $conf['mail'], $conf['mail'], $conf['mail']);
                    }

                    $info = $this->_cms_lang->getLanguageValue('qc_message_abgeschickt');
                    $message_succes = true;
                    $is_send = false; // Formularausfüllung mit alten Werten verhindern
                    // Aktuelle Zeit merken
                    $_SESSION['commentform_load'] = time();
                }
            }
        } else {
            // Session neu generieren
            $_SESSION['commentform_schreiber'] = time()-rand(30, 40);
            $_SESSION['commentform_text'] = time()-rand(10, 20);
            $_SESSION['commentform_number'] = time()-rand(0, 10);
            $_SESSION['commentform_arithmetic'] = time()-rand(20, 30);

            if ($delete_request_send <> '') {
                $info = $this->_cms_lang->getLanguageValue('qc_message_geloescht');
                $message_succes = true;
            }
        }


        // Anti Spam Captcha Zahlen generieren
        $zahl_1 = intval(rand(0, 10));
        $zahl_2 = intval(rand(0, 10));


        // Zeichenlimit des Kommentarfeldes
        // --------------------------------
        $jscript = '';
        if ($conf['zeichenbegrenzung'] == 'true') {
            $jscript .= '
                <script language="JavaScript">
                    function charcount() {
                      var limit = ' . $conf['zeichenanzahl'] . ';
                      var txt = document.getElementById("qc_text").value;
                      if (txt.length > limit) {
                        document.getElementById("qc_text").value = txt.substring(0,limit);
                        document.getElementById("charsleft").innerHTML = "0";
                        document.getElementById("charsleft").style.color = "red";
                      } else { document.getElementById("charsleft").innerHTML = limit - txt.length; }
                      if (txt.length+10 > limit) {
                        document.getElementById("charsleft").style.color = "red";
                      }
                      if (txt.length+10 <= limit) {
                        document.getElementById("charsleft").style.color = "inherit";
                      }
                    }
                </script>';
        }

        // formula elements
        // ------------------

        // messages
        $msg = '';
        $msg .= '<div id="qc_info';
        if ($message_error) $msg .= '_error';
        else if ($message_succes) $msg .= '_succes';
        $msg .= '">'.$info.'</div>';

        // input name
        $inputname = '';
        $inputname .= '<label>'.$this->_cms_lang->getLanguageValue('qc_form_name').' </label>
                    <input id="qc_name" name="'.$_SESSION['commentform_schreiber'].'" type="text" value="';
                    if ($is_send) $inputname .= $get_schreiber;
        $inputname .= '"/>';

        // input comment
        $inputcomment = '';
        $inputcomment .= '<label>'.$this->_cms_lang->getLanguageValue('qc_form_kommentar').' </label>
                    <textarea name="'.$_SESSION['commentform_text'].'" ';
                    if ($conf['zeichenbegrenzung'] == 'true') $inputcomment .= 'onkeyup="charcount()" ';
        $inputcomment .= 'id="qc_text">';
                    if ($is_send) $inputcomment .= $get_text;
        $inputcomment .= '</textarea>';

        // character limit
        $charlimit = '';
        if ($conf['zeichenbegrenzung'] == 'true') {
            $charlimit .= '<div id="charsleft">'.$conf['zeichenanzahl'].'</div>
            <script language="javascript">charcount();</script>';
        }

        // input spam protection
        $spamprotection = '';
        if ($conf['spamschutz'] == 'true') {
            $spamprotection .= '<label>' . $this->_cms_lang->getLanguageValue('qc_form_spamschutz'). '  ' . $zahl_1 . ' + ' . $zahl_2 . ' =</label>
                        <input name="'.$_SESSION['commentform_number'].'" type="hidden" value="'.md5(( $zahl_1 + $zahl_2 )).'" />
                        <input id="qc_arithmetic" name="'.$_SESSION['commentform_arithmetic'].'" type="text" />';
        }

        // submit
        $inputsubmit = '';
        $inputsubmit .= '<input id="qc_submit" name="submit" type="submit" value="'.$this->_cms_lang->getLanguageValue('qc_form_submit').'" />';

        // build form
        $kform = '';
        $kform .= '<form action="#qc_container" method="post" name="qcform" id="qc_form">';

        $kform .= $conf['formtemplate'];
        $kform = str_replace(
            array(
                "{MESSAGE}",
                "{NAME}",
                "{COMMENT}",
                "{CHARLIMIT}",
                "{SPAM}",
                "{SUBMIT}"
            ),
            array(
                $msg,
                $inputname,
                $inputcomment,
                $charlimit,
                $spamprotection,
                $inputsubmit
            ),
            $kform
        );

        $kform .= '</form>';


        // Kommentarliste
        // --------------
        $kommentare = '';
        $kanzahl = 0;
        if (file_exists($filename)) {
            // Zeilen der Kommentardatei auslesen und Umbrüche behandeln
            $lines = file($filename);
            for ($i=0;$i<count($lines);$i++) {
                $lines[$i] = rtrim($lines[$i]);
            }
            $lines[count($lines)-1] = $lines[count($lines)-1]."\n";

            // Wenn Delete-Formular abgesendet -> entsprechende Zeile aus der Kommentardatei löschen
            if (
                $delete_request_send <> ''
                and isset($_SESSION['AC_LOGIN_STATUS'])
                and isset($_SESSION['AC_LOGGED_USER_IN'])
                and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok'
                and $_SESSION['AC_LOGGED_USER_IN'] == $conf['adminuser']
            ) {
                // Wenn nur noch ein Kommentar vorhanden -> Datei ganz löschen
                if (count($lines)<2) {
                    unlink($filename);
                } else {
                    unset($lines[$get_delete_id]);
                    $lines = array_values($lines);
                    $dateineu = trim(implode("\n", $lines)) . "\n";
                    $qcdatei = fopen($filename,'w') or die($this->_cms_lang->getLanguageValue('qc_message_fehler'));
                    fwrite($qcdatei, $dateineu);
                    fclose($qcdatei);
                }
            }

            // Wenn die Datei nach dem Löschen immer noch existiert
            if (file_exists($filename)) {
                $delete_button = '';
                // Neue Einträge unten
                if ($conf['sortierung'] == 'neueunten') {
                    foreach ($lines as $line) {
                        // Wenn als Admin eingeloggt -> Delete Buttons zeigen
                        if (
                            isset($_SESSION['AC_LOGIN_STATUS'])
                            and isset($_SESSION['AC_LOGGED_USER_IN'])
                            and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok'
                            and $_SESSION['AC_LOGGED_USER_IN'] == $conf['adminuser']
                        ) {
                            $delete_button = '<a class="delete" tabindex="'.$kanzahl.'">X'.'<span><form action="#qc_container" method="post" name="qcdelete" class="qc_delete"><input name="delete_id" type="hidden" value="'.$kanzahl.'" />'.$this->_cms_lang->getLanguageValue('qc_delete_question').' <input class="qc_delete_submit" name="delete_submit" type="submit" value="'.$this->_cms_lang->getLanguageValue('qc_delete_submit').'" /></form></span></a>';
                        }
                        $elements = explode('§',$line);
                        $kommentare .= '<div class="quickcomment"><div class="qc_head"><div class="qc_name">'.$elements[0].'</div>'.$delete_button.'<div class="qc_date">'.date($conf['date'] . $conf['datumtrenner'] . $conf['time'],$elements[1]).'</div></div><div class="qc_text">'.trim($elements[2]).'</div></div>';
                        $kanzahl++;
                    }
                // Neue Einträge oben
                } else if ($conf['sortierung'] == 'neueoben') {
                    foreach (array_reverse($lines) as $line) {
                        // Wenn als Admin eingeloggt -> Delete Buttons zeigen
                        $reverse_kanzahl = count($lines)-1-$kanzahl;
                        if (
                            isset($_SESSION['AC_LOGIN_STATUS'])
                            and isset($_SESSION['AC_LOGGED_USER_IN'])
                            and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok'
                            and $_SESSION['AC_LOGGED_USER_IN'] == $conf['adminuser']
                        ) {
                            $delete_button = '<a class="delete" tabindex="'.$reverse_kanzahl.'">X'.'<span><form action="#qc_container" method="post" name="qcdelete" class="qc_delete"><input name="delete_id" type="hidden" value="'.$reverse_kanzahl.'" />'.$this->_cms_lang->getLanguageValue('qc_delete_question').' <input class="qc_delete_submit" name="delete_submit" type="submit" value="'.$this->_cms_lang->getLanguageValue('qc_delete_submit').'" /></form></span></a>';
                        }
                        $elements = explode('§',$line);
                        $kommentare .= '<div class="quickcomment"><div class="qc_head"><div class="qc_name">'.$elements[0].'</div>'.$delete_button.'<div class="qc_date">'.date($conf['date'] . $conf['datumtrenner'] . $conf['time'],$elements[1]).'</div></div><div class="qc_text">'.trim($elements[2]).'</div></div>';
                        $kanzahl++;
                    }
                } else $kommentare .= $this->_cms_lang->getLanguageValue('qc_form_sortierung');
            }
        }

        // Überschrift
        // -----------
        if ($kanzahl==1) {
            $headline = '<h2>'.$kanzahl.' '.$this->_cms_lang->getLanguageValue('qc_headline_sin').'</h2>';
        }
        else {
            $headline = '<h2>'.$kanzahl.' '.$this->_cms_lang->getLanguageValue('qc_headline_plu').'</h2>';
        }

        // Rückgabe
        // --------
        $return = '';
        $return .= $jscript;
        $return .= '<div id="qc_container">';
        $return .= $headline;
        if ($conf['formanordnung'] == 'oben') $return .= $kform . $kommentare;
        else $return .= $kommentare . $kform;
        $return .= '</div>';

        return $return;

    }


    /**
     * sets backend configuration elements and template
     *
     * @return Array configuration
     */
    function getConfig()
    {
        $config = array();

        // read configuration values
        foreach ($this->_confdefault as $key => $value) {
            // handle each form type
            switch ($value[1]) {
            case 'text':
                $config[$key] = $this->confText(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    )
                );
                break;

            case 'textarea':
                $config[$key] = $this->confTextarea(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    )
                );
                break;

            case 'password':
                $config[$key] = $this->confPassword(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    ),
                    $value[5]
                );
                break;

            case 'check':
                $config[$key] = $this->confCheck(
                    $this->_admin_lang->getLanguageValue('config_' . $key)
                );
                break;

            case 'radio':
                $descriptions = array();
                foreach ($value[2] as $label) {
                    $descriptions[$label] = $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_' . $label
                    );
                }
                $config[$key] = $this->confRadio(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $descriptions
                );
                break;

            case 'select':
                $descriptions = array();
                foreach ($value[2] as $label) {
                    $descriptions[$label] = $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_' . $label
                    );
                }
                $config[$key] = $this->confSelect(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $descriptions,
                    $value[3]
                );
                break;

            default:
                break;
            }
        }

        // read admin.css
        $admin_css = '';
        $lines = file('../plugins/' . self::PLUGIN_TITLE. '/admin.css');
        foreach ($lines as $line_num => $line) {
            $admin_css .= trim($line);
        }

        // add template CSS
        $template = '<style>' . $admin_css . '</style>';

        // build Template
        $template .= '
            <div class="quickcomment-admin-header">
            <span>'
                . $this->_admin_lang->getLanguageValue(
                    'admin_header',
                    self::PLUGIN_TITLE
                )
            . '</span>
            <a href="' . self::PLUGIN_DOCU . '" target="_blank">
            <img style="float:right;" src="' . self::LOGO_URL . '" />
            </a>
            </div>
        </li>
        <li class="mo-in-ul-li ui-widget-content quickcomment-admin-li">
            <div class="quickcomment-admin-subheader">'
            . $this->_admin_lang->getLanguageValue('admin_test')
            . '</div>
            <div style="margin-bottom:5px;">
                <div class="quickcomment-single-conf">
                    {test1_text}
                </div>
                {test1_description}
                <span class="quickcomment-admin-default">
                    [' . /*$this->_confdefault['test1'][0] .*/']
                </span>
            </div>
            <div style="margin-bottom:5px;">
                <div class="quickcomment-single-conf">
                    {test2_text}
                </div>
                {test2_description}
                <span class="quickcomment-admin-default">
                    [' . /*$this->_confdefault['test2'][0] .*/']
                </span>
        ';

        $config['--template~~'] = $template;

        return $config;
    }

    /**
     * sets default backend configuration elements, if no plugin.conf.php is
     * created yet
     *
     * @return Array configuration
     */
    function getDefaultSettings()
    {
        $config = array('active' => 'true');
        foreach ($this->_confdefault as $elem => $default) {
            $config[$elem] = $default[0];
        }
        return $config;
    }

    /**
     * sets backend plugin information
     *
     * @return Array information
     */
    function getInfo()
    {
        global $ADMIN_CONF;

        $this->_admin_lang = new Language(
            $this->PLUGIN_SELF_DIR
            . 'lang/admin_language_'
            . $ADMIN_CONF->get('language')
            . '.txt'
        );

        // build plugin tags
        $tags = array();
        foreach ($this->_plugin_tags as $key => $tag) {
            $tags[$tag] = $this->_admin_lang->getLanguageValue('tag_' . $key);
        }

        $info = array(
            '<b>' . self::PLUGIN_TITLE . '</b> ' . self::PLUGIN_VERSION,
            self::MOZILO_VERSION,
            $this->_admin_lang->getLanguageValue(
                'description',
                htmlspecialchars($this->_plugin_tags['tag1'], ENT_COMPAT, 'UTF-8')
            ),
            self::PLUGIN_AUTHOR,
            array(
                self::PLUGIN_DOCU,
                self::PLUGIN_TITLE . ' '
                . $this->_admin_lang->getLanguageValue('on_devmount')
            ),
            $tags
        );

        return $info;
    }

    /**
     * creates configuration for text fields
     *
     * @param string $description Label
     * @param string $maxlength   Maximum number of characters
     * @param string $size        Size
     * @param string $regex       Regular expression for allowed input
     * @param string $regex_error Wrong input error message
     *
     * @return Array  Configuration
     */
    protected function confText(
        $description,
        $maxlength = '',
        $size = '',
        $regex = '',
        $regex_error = ''
    ) {
        // required properties
        $conftext = array(
            'type' => 'text',
            'description' => $description,
        );
        // optional properties
        if ($maxlength != '') {
            $conftext['maxlength'] = $maxlength;
        }
        if ($size != '') {
            $conftext['size'] = $size;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        if ($regex_error != '') {
            $conftext['regex_error'] = $regex_error;
        }
        return $conftext;
    }

    /**
     * creates configuration for textareas
     *
     * @param string $description Label
     * @param string $cols        Number of columns
     * @param string $rows        Number of rows
     * @param string $regex       Regular expression for allowed input
     * @param string $regex_error Wrong input error message
     *
     * @return Array  Configuration
     */
    protected function confTextarea(
        $description,
        $cols = '',
        $rows = '',
        $regex = '',
        $regex_error = ''
    ) {
        // required properties
        $conftext = array(
            'type' => 'textarea',
            'description' => $description,
        );
        // optional properties
        if ($cols != '') {
            $conftext['cols'] = $cols;
        }
        if ($rows != '') {
            $conftext['rows'] = $rows;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        if ($regex_error != '') {
            $conftext['regex_error'] = $regex_error;
        }
        return $conftext;
    }

    /**
     * creates configuration for password fields
     *
     * @param string  $description Label
     * @param string  $maxlength   Maximum number of characters
     * @param string  $size        Size
     * @param string  $regex       Regular expression for allowed input
     * @param string  $regex_error Wrong input error message
     * @param boolean $saveasmd5   Safe password as md5 (recommended!)
     *
     * @return Array   Configuration
     */
    protected function confPassword(
        $description,
        $maxlength = '',
        $size = '',
        $regex = '',
        $regex_error = '',
        $saveasmd5 = true
    ) {
        // required properties
        $conftext = array(
            'type' => 'text',
            'description' => $description,
        );
        // optional properties
        if ($maxlength != '') {
            $conftext['maxlength'] = $maxlength;
        }
        if ($size != '') {
            $conftext['size'] = $size;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        $conftext['saveasmd5'] = $saveasmd5;
        return $conftext;
    }

    /**
     * creates configuration for checkboxes
     *
     * @param string $description Label
     *
     * @return Array  Configuration
     */
    protected function confCheck($description)
    {
        // required properties
        return array(
            'type' => 'checkbox',
            'description' => $description,
        );
    }

    /**
     * creates configuration for radio buttons
     *
     * @param string $description  Label
     * @param string $descriptions Array Single item labels
     *
     * @return Array Configuration
     */
    protected function confRadio($description, $descriptions)
    {
        // required properties
        return array(
            'type' => 'select',
            'description' => $description,
            'descriptions' => $descriptions,
        );
    }

    /**
     * creates configuration for select fields
     *
     * @param string  $description  Label
     * @param string  $descriptions Array Single item labels
     * @param boolean $multiple     Enable multiple item selection
     *
     * @return Array Configuration
     */
    protected function confSelect($description, $descriptions, $multiple = false)
    {
        // required properties
        return array(
            'type' => 'select',
            'description' => $description,
            'descriptions' => $descriptions,
            'multiple' => $multiple,
        );
    }

    /**
     * throws styled error message
     *
     * @param string $text Content of error message
     *
     * @return string HTML content
     */
    protected function throwError($text)
    {
        return '<div class="' . self::PLUGIN_TITLE . 'Error">'
            . '<div>' . $this->_cms_lang->getLanguageValue('error') . '</div>'
            . '<span>' . $text. '</span>'
            . '</div>';
    }

    /**
     * throws styled success message
     *
     * @param string $text Content of success message
     *
     * @return string HTML content
     */
    protected function throwSuccess($text)
    {
        return '<div class="' . self::PLUGIN_TITLE . 'Success">'
            . '<div>' . $this->_cms_lang->getLanguageValue('success') . '</div>'
            . '<span>' . $text. '</span>'
            . '</div>';
    }

}

?>