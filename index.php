<?php if(!defined('IS_CMS')) die();

/**
 * Plugin:   quickComment
 * @author:  HPdesigner (hpdesigner[at]web[dot]de)
 * @version: v1.1.2013-09-19
 * @license: GPL
 * @see:     I can do all this through him who gives me strength.
 *           - The Bible
 *
 * Plugin created by DEVMOUNT
 * www.devmount.de
 *
**/

class quickComment extends Plugin {

	public $admin_lang;
	private $cms_lang;

	function getContent($value) {

		global $CMS_CONF;
		global $CatPage;
		
		$this->cms_lang = new Language(PLUGIN_DIR_REL.'quickComment/sprachen/cms_language_'.$CMS_CONF->get('cmslanguage').'.txt');
			
		// read config
		$conf_sortierung 		= $this->settings->get('sortierung');
		$conf_anordnung 		= $this->settings->get('formanordnung');
		$conf_formtemplate 		= $this->settings->get('form_template');
		$conf_spamschutz 		= $this->settings->get('spamschutz');
		$conf_zeichenanzahl 	= $this->settings->get('zeichenanzahl');
		$conf_zeichenbegrenzung = $this->settings->get('zeichenbegrenzung');
		$conf_date 				= $this->settings->get('date');
		$conf_time 				= $this->settings->get('time');
		$conf_datumtrenner 		= $this->settings->get('datumtrenner');
		$conf_reloadsperre 		= $this->settings->get('reloadsperre');
		$conf_mail 				= $this->settings->get('mail');
		$conf_breaksincomments 	= $this->settings->get('breaksincomments');
		$conf_adminuser 		= $this->settings->get('adminuser');

		if (($conf_reloadsperre == '') || !preg_match("/^[\d+]+$/", $conf_reloadsperre)) $conf_reloadsperre = 600;

		// set default conf values
		if (!isset($conf_date)) $conf_date = 'd.m.y';
		if (!isset($conf_time)) $conf_time = 'H:i';
		if (!isset($conf_datumtrenner)) $conf_datumtrenner = ' ';

		
		// build filename used for $value specific database
		$file = 'qc_'.$value.'.txt';
		$filename = 'plugins/quickComment/'.$file;

		// Formulardaten einlesen
		$get_schreiber = '';
		$get_text = '';
		$get_number = '';
		$get_arithmetic = '';
		$is_send = getRequestValue('submit', false);
		if (isset($_SESSION['commentform_schreiber'])) 	$get_schreiber = trim(getRequestValue($_SESSION['commentform_schreiber'], false));
		if (isset($_SESSION['commentform_text'])) 		$get_text = trim(getRequestValue($_SESSION['commentform_text'], false));
		if (isset($_SESSION['commentform_number'])) 	$get_number = trim(getRequestValue($_SESSION['commentform_number'], false));
		if (isset($_SESSION['commentform_arithmetic'])) $get_arithmetic = trim(getRequestValue($_SESSION['commentform_arithmetic'], false));
		
		// Delete-Formular Daten einlesen
		$get_delete_id = getRequestValue('delete_id', false);
		$delete_request_send = getRequestValue('delete_submit', false);

		// Kodierung handlen
		$get_schreiber = urldecode($get_schreiber);
		$get_text = urldecode($get_text);	

		// check number of characters
		if (isset($conf_zeichenanzahl) and mb_strlen($get_text) > $conf_zeichenanzahl+30)
			$get_text = substr($get_text, 0, $conf_zeichenanzahl+30);

		// Nachricht und Nachrichttypen initialisieren
		$info = $this->cms_lang->getLanguageValue('qc_message_allefelder');
		$message_error = false;
		$message_succes = false;

		// Formular wurde abgesendet
		if($is_send <> '') {
			// Reloadsperre
			if (time() - $_SESSION['commentform_load'] < $conf_reloadsperre) {
					$info = $this->cms_lang->getLanguageValue('qc_message_reload', $conf_reloadsperre); $message_error = true;
			}
			// Fehlende Formulareingaben handlen
			else if (trim($get_text) == '' && trim($get_schreiber) == '') { $info = $this->cms_lang->getLanguageValue('qc_message_allefelder'); $message_error = true; } 
			else if (trim($get_text) == '') { $info = $this->cms_lang->getLanguageValue('qc_message_keinkommentar'); $message_error = true; } 
			else if (trim($get_schreiber) == '') { $info = $this->cms_lang->getLanguageValue('qc_message_keinname'); $message_error = true; } 
			else if (trim($get_text) != '' && trim($get_schreiber) != '') {
				// Spamschutzüberprüfung
				if($conf_spamschutz == 'true' && $get_number != md5($get_arithmetic)){ $info = $this->cms_lang->getLanguageValue('qc_message_spamfalsch'); $message_error = true; } 
				// Alles in Ordnung
				else {
					// Datei schreiben
					$datei = fopen($filename,'a+') or die($this->cms_lang->getLanguageValue('qc_message_fehler'));
					if ($datei < 0 ) { $info = $this->cms_lang->getLanguageValue('qc_message_nichtgesendet');	$message_error = true; } 
					#$date1 = date($conf_date);
					#$date2 = date($conf_time);
					$tstamp = time();
					
					// Zeilenumbrüche in der Textarea je nach conf handlen
					if($conf_breaksincomments == 'true') $get_text = str_replace("\n",'<br />',$get_text);
					else $get_text = str_replace("\n",' ',$get_text);
					$get_text = str_replace("\r",'',$get_text);
					
					$inhalt = sprintf("%s§%s§%s\n",str_replace('§','&sect;',$get_schreiber),$tstamp,str_replace('§','&sect;',$get_text)); 
					fwrite($datei, $inhalt);
					fclose($datei);
					// chown($filename, 2545);
					
					// falls gesetzt, Bestätigungsmail senden
					if ($conf_mail <> '') {
						$mailsubject = $this->cms_lang->getLanguageValue('qc_mail_subject', $CMS_CONF->get('websitetitle'), $CatPage->get_HrefText(CAT_REQUEST,PAGE_REQUEST));
						$mailcontent = $this->cms_lang->getLanguageValue('qc_mail_content_head', utf8_decode($get_schreiber), date($conf_date . $conf_datumtrenner . $conf_time,$tstamp)."\r\n");
						$mailcontent .= utf8_decode($get_text)."\r\n".$this->cms_lang->getLanguageValue('qc_mail_content_text', $file, 'www.'.$_SERVER['SERVER_NAME'].$CatPage->get_Href(CAT_REQUEST,PAGE_REQUEST));
						require_once(BASE_DIR_CMS.'Mail.php');
						sendMail($mailsubject, $mailcontent, $conf_mail, $conf_mail, $conf_mail);
					}
					
					$info = $this->cms_lang->getLanguageValue('qc_message_abgeschickt');
					$message_succes = true;
					$is_send = false; // Formularausfüllung mit alten Werten verhindern
					// Aktuelle Zeit merken
					$_SESSION['commentform_load'] = time();
					;
				} 
			} 
		} else {
			// Session neu generieren
			$_SESSION['commentform_schreiber'] = time()-rand(30, 40);
			$_SESSION['commentform_text'] = time()-rand(10, 20);
			$_SESSION['commentform_number'] = time()-rand(0, 10);
			$_SESSION['commentform_arithmetic'] = time()-rand(20, 30);
			
			if($delete_request_send <> '') {
				$info = $this->cms_lang->getLanguageValue('qc_message_geloescht');
				$message_succes = true;
			}
		}
		
		
		// Anti Spam Captcha Zahlen generieren
		$zahl_1 = intval(rand(0, 10));
		$zahl_2 = intval(rand(0, 10));
						
			
		// Zeichenlimit des Kommentarfeldes
		// --------------------------------
		$jscript = '';
		if($conf_zeichenbegrenzung == 'true') {
			$jscript .= '
				<script language="JavaScript">
					function charcount() {
					  var limit = ' . $conf_zeichenanzahl . ';
					  var txt = document.getElementById("qc_text").value;
					  if (txt.length > limit) {
						document.getElementById("qc_text").value = txt.substring(0,limit);
						document.getElementById("charsleft").innerHTML = "0";
						document.getElementById("charsleft").style.color = "red";
					  } else { document.getElementById("charsleft").innerHTML = limit - txt.length; }
					  if (txt.length+10 > limit) { document.getElementById("charsleft").style.color = "red"; }
					  if (txt.length+10 <= limit) { document.getElementById("charsleft").style.color = "inherit"; }
					}
				</script>';
		}
				
		// formula elements
		// ------------------

		// messages
		$msg = '';
		$msg .= '<div id="qc_info';
		if($message_error) $msg .= '_error';
		else if($message_succes) $msg .= '_succes';
		$msg .= '">'.$info.'</div>';

		// input name
		$inputname = '';
		$inputname .= '<label>'.$this->cms_lang->getLanguageValue('qc_form_name').' </label>
					<input id="qc_name" name="'.$_SESSION['commentform_schreiber'].'" type="text" value="';
					if($is_send) $inputname .= $get_schreiber;
		$inputname .= '"/>';

		// input comment
		$inputcomment = '';
		$inputcomment .= '<label>'.$this->cms_lang->getLanguageValue('qc_form_kommentar').' </label>
					<textarea name="'.$_SESSION['commentform_text'].'" ';
					if($conf_zeichenbegrenzung == 'true') $inputcomment .= 'onkeyup="charcount()" ';
		$inputcomment .= 'id="qc_text">';
					if($is_send) $inputcomment .= $get_text; 
		$inputcomment .= '</textarea>';

		// character limit
		$charlimit = '';
		if($conf_zeichenbegrenzung == 'true') {
			$charlimit .= '<div id="charsleft">'.$conf_zeichenanzahl.'</div>
			<script language="javascript">charcount();</script>';
		}

		// input spam protection
		$spamprotection = '';
		if($conf_spamschutz == 'true') {
			$spamprotection .= '<label>' . $this->cms_lang->getLanguageValue('qc_form_spamschutz'). '  ' . $zahl_1 . ' + ' . $zahl_2 . ' =</label>
						<input name="'.$_SESSION['commentform_number'].'" type="hidden" value="'.md5(( $zahl_1 + $zahl_2 )).'" />
						<input id="qc_arithmetic" name="'.$_SESSION['commentform_arithmetic'].'" type="text" />';
		}

		// submit
		$inputsubmit = '';
		$inputsubmit .= '<input id="qc_submit" name="submit" type="submit" value="'.$this->cms_lang->getLanguageValue('qc_form_submit').'" />';

		// build form
		$kform = '';
		$kform .= '<form action="#qc_container" method="post" name="qcform" id="qc_form">';

		if (isset($conf_formtemplate)) {
			$kform .= $conf_formtemplate;
			$kform = str_replace(
				array("{MESSAGE}","{NAME}","{COMMENT}","{CHARLIMIT}","{SPAM}","{SUBMIT}"),
				array($msg, $inputname, $inputcomment, $charlimit, $spamprotection, $inputsubmit),
				$kform
			);
		} else $kform .= $msg . $inputname . $inputcomment . $charlimit . $spamprotection . $inputsubmit;
		
		$kform .= '</form>';


		// Kommentarliste
		// --------------
		$kommentare = '';
		$kanzahl = 0;
		if(file_exists($filename)) {
			// Zeilen der Kommentardatei auslesen und Umbrüche behandeln
			$lines = file($filename);
			for ($i=0;$i<count($lines);$i++) $lines[$i] = rtrim($lines[$i]);
			$lines[count($lines)-1] = $lines[count($lines)-1]."\n";
			
			// Wenn Delete-Formular abgesendet -> entsprechende Zeile aus der Kommentardatei löschen
			if($delete_request_send <> '' and isset($_SESSION['AC_LOGIN_STATUS']) and isset($_SESSION['AC_LOGGED_USER_IN']) and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok' and $_SESSION['AC_LOGGED_USER_IN'] == $conf_adminuser) {
				// Wenn nur noch ein Kommentar vorhanden -> Datei ganz löschen
				if (count($lines)<2) {
					unlink($filename);				        		
				} else {
					unset($lines[$get_delete_id]);
					$lines = array_values($lines);
					$dateineu = trim(implode("\n", $lines)) . "\n";
					$qcdatei = fopen($filename,'w') or die($this->cms_lang->getLanguageValue('qc_message_fehler'));
					fwrite($qcdatei, $dateineu);
					fclose($qcdatei);				        		
				}
			}

			// Wenn die Datei nach dem Löschen immer noch existiert
			if(file_exists($filename)) {
				$delete_button = '';
				// Neue Einträge unten
				if($conf_sortierung == 'neueunten') {
					foreach ($lines as $line) {
						// Wenn als Admin eingeloggt -> Delete Buttons zeigen
						if(isset($_SESSION['AC_LOGIN_STATUS']) and isset($_SESSION['AC_LOGGED_USER_IN']) and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok' and $_SESSION['AC_LOGGED_USER_IN'] == $conf_adminuser) {
							$delete_button = '<a class="delete" tabindex="'.$kanzahl.'">X'.'<span><form action="#qc_container" method="post" name="qcdelete" class="qc_delete"><input name="delete_id" type="hidden" value="'.$kanzahl.'" />'.$this->cms_lang->getLanguageValue('qc_delete_question').' <input class="qc_delete_submit" name="delete_submit" type="submit" value="'.$this->cms_lang->getLanguageValue('qc_delete_submit').'" /></form></span></a>';
						}
						$elements = explode('§',$line);
						$kommentare .= '<div class="quickcomment"><div class="qc_head"><div class="qc_name">'.$elements[0].'</div>'.$delete_button.'<div class="qc_date">'.date($conf_date . $conf_datumtrenner . $conf_time,$elements[1]).'</div></div><div class="qc_text">'.trim($elements[2]).'</div></div>';
						$kanzahl++;
					}
				// Neue Einträge oben
				} else if($conf_sortierung == 'neueoben') {
					foreach (array_reverse($lines) as $line) {
						// Wenn als Admin eingeloggt -> Delete Buttons zeigen
						$reverse_kanzahl = count($lines)-1-$kanzahl;
						if(isset($_SESSION['AC_LOGIN_STATUS']) and isset($_SESSION['AC_LOGGED_USER_IN']) and $_SESSION['AC_LOGIN_STATUS'] == 'login_ok' and $_SESSION['AC_LOGGED_USER_IN'] == $conf_adminuser) {
							$delete_button = '<a class="delete" tabindex="'.$reverse_kanzahl.'">X'.'<span><form action="#qc_container" method="post" name="qcdelete" class="qc_delete"><input name="delete_id" type="hidden" value="'.$reverse_kanzahl.'" />'.$this->cms_lang->getLanguageValue('qc_delete_question').' <input class="qc_delete_submit" name="delete_submit" type="submit" value="'.$this->cms_lang->getLanguageValue('qc_delete_submit').'" /></form></span></a>';
						}
						$elements = explode('§',$line);
						$kommentare .= '<div class="quickcomment"><div class="qc_head"><div class="qc_name">'.$elements[0].'</div>'.$delete_button.'<div class="qc_date">'.date($conf_date . $conf_datumtrenner . $conf_time,$elements[1]).'</div></div><div class="qc_text">'.trim($elements[2]).'</div></div>';
						$kanzahl++;
					}
				} else $kommentare .= $this->cms_lang->getLanguageValue('qc_form_sortierung');
			}
		}

		// Überschrift
		// -----------
		if($kanzahl==1) $headline = '<h2>'.$kanzahl.' '.$this->cms_lang->getLanguageValue('qc_headline_sin').'</h2>';
		else $headline = '<h2>'.$kanzahl.' '.$this->cms_lang->getLanguageValue('qc_headline_plu').'</h2>';
		
		// Rückgabe
		// --------
		$return = '';
		$return .= $jscript;
		$return .= '<div id="qc_container">';
		$return .= $headline;
		if($conf_anordnung == 'oben') $return .= $kform.$kommentare;
		else $return .= $kommentare.$kform;
		$return .= '</div>';

		return $return;

	}
	
	
	function getConfig() {

		$config = array();
   
		// Spamschutz aktivieren
		$config['spamschutz'] = array(
			'type' => 'checkbox',
			'description' => $this->admin_lang->getLanguageValue('config_spamschutz_qc')
		);

		// Zeichenbegrenzung für das Kommentarfeld aktivieren
		$config['zeichenbegrenzung'] = array(
			'type' => 'checkbox',
			'description' => $this->admin_lang->getLanguageValue('config_zeichenbegrenzung_qc')
		);
		
		// Zeichenbegrenzung für das Kommentarfeld festlegen
		$config['zeichenanzahl']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_zeichenanzahl_qc'),
			'maxlength' => '100',
			'size' => '3',
			'regex' => "/^[0-9]{2,3}$/",
			'regex_error' => $this->admin_lang->getLanguageValue('config_zeichenanzahl_error')
		);
		
		// Zeilenumbrüche in Kommentaren erlauben
		$config['breaksincomments'] = array(
			'type' => 'checkbox',
			'description' => $this->admin_lang->getLanguageValue('config_breaksincomments_qc')
		);
		
		// Datumsformat
		$config['date']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_date_qc'),
			'maxlength' => '100',
			'size' => '3'
		);

		// Uhrzeitformat
		$config['time']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_time_qc'),
			'maxlength' => '100',
			'size' => '3'
		);
		
		// Reloadsperre in Sekunden
		$config['reloadsperre']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_reloadsperre_qc'),
			'maxlength' => '100',
			'size' => '3',
			'regex' => "/^[0-9]{0,4}$/",
			'regex_error' => $this->admin_lang->getLanguageValue('config_reloadsperre_error')
		); 
			   
		// Symbol für Trennung von Datum und Uhrzeit festlegen
		$config['datumtrenner']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_datumtrenner_qc'),
			'maxlength' => '100',
			'size' => '3',
			'regex' => "/^[^A-Za-z0-9]{1,8}$/",
			'regex_error' => $this->admin_lang->getLanguageValue('config_datumtrenner_error')
		);

		// Sortierung  nach Datum (aufsteigend | absteigend) auswählen
		$config['sortierung'] = array(
			'type' => 'radio',
			'description' => $this->admin_lang->getLanguageValue('config_sortierung_qc'),
			'descriptions' => array(
				'neueoben' => $this->admin_lang->getLanguageValue('config_sortierung_neueoben_qc'),
				'neueunten' => $this->admin_lang->getLanguageValue('config_sortierung_neueunten_qc')
				)
		); 

		$config['form_template']  = array(
			"type" => "textarea",
			"rows" => "5",
			"description" => $this->admin_lang->getLanguageValue("config_form_template"),
			'template' => '{form_template_description}<br />{form_template_textarea}'
		);
		
		// Anordnung des Kommentarformulars
		$config['formanordnung'] = array(
			'type' => 'radio',
			'description' => $this->admin_lang->getLanguageValue('config_anordnung_qc'),
			'descriptions' => array(
				'oben' => $this->admin_lang->getLanguageValue('config_anordnung_oben_qc'),
				'unten' => $this->admin_lang->getLanguageValue('config_anordnung_unten_qc')
				)
		);
		
		// Benachrichtigung per Mail über neue Kommentare
		$config['mail']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_mail_qc'),
			'maxlength' => '100',
			'size' => '40',
			'regex' => "/^[\w-]+(\.[\w-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\.)+([a-z]{2,4})$/i",
			'regex_error' => $this->admin_lang->getLanguageValue('config_mail_error')
		);
		
		// Adminuser für Frontendediting
		$config['adminuser']  = array(
			'type' => 'text',
			'description' => $this->admin_lang->getLanguageValue('config_adminuser_qc'),
			'maxlength' => '100',
			'size' => '40'
		);

		return $config;

	} 
	
	
	function getInfo() {
		global $ADMIN_CONF;

		$this->admin_lang = new Language(PLUGIN_DIR_REL.'quickComment/sprachen/admin_language_'.$ADMIN_CONF->get('language').'.txt');

		$info = array(
			// Plugin-Name + Version
			'<b>quickComment</b> v1.1.2013-09-19',
			// moziloCMS-Version
			'2.0',
			// Kurzbeschreibung nur <span> und <br /> sind erlaubt
			$this->admin_lang->getLanguageValue('config_description_qc'),
			// Name des Autors
			'HPdesigner',
			// Download-URL
			'http://www.devmount.de/Develop/Mozilo%20Plugins/quickComment.html',
			// Platzhalter für die Selectbox in der Editieransicht, kann leer sein
			array(
				'{quickComment|name}' => $this->admin_lang->getLanguageValue('toolbar_platzhalter_qc')
			)
		);

		return $info;
		
	}
	
}

?>