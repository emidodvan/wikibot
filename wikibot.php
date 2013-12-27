<?php
/**
 * Esta clase está designada para proporcionar una simple interfaz cURL que mantiene las cookies.
 * @author Emilio D. Tejedor
 **/
class http {
    private $ch;
    private $uid;
    public $cookie_jar;
    public $postfollowredirs;
    public $getfollowredirs;
    public $quiet=false;

	public function http_code () {
		return curl_getinfo( $this->ch, CURLINFO_HTTP_CODE );
	}

    function data_encode ($data, $keyprefix = "", $keypostfix = "") {
        assert( is_array($data) );
        $vars=null;
        foreach($data as $key=>$value) {
            if(is_array($value))
                $vars .= $this->data_encode($value, $keyprefix.$key.$keypostfix.urlencode("["), urlencode("]"));
            else
                $vars .= $keyprefix.$key.$keypostfix."=".urlencode($value)."&";
        }
        return $vars;
    }

    function __construct () {
        $this->ch = curl_init();
        $this->uid = dechex(rand(0,99999999));
        curl_setopt($this->ch,CURLOPT_COOKIEJAR,'/tmp/wikibot.cookies.'.$this->uid.'.dat');
        curl_setopt($this->ch,CURLOPT_COOKIEFILE,'/tmp/wikibot.cookies.'.$this->uid.'.dat');
        curl_setopt($this->ch,CURLOPT_MAXCONNECTS,100);
        curl_setopt($this->ch,CURLOPT_CLOSEPOLICY,CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
        $this->postfollowredirs = 0;
        $this->getfollowredirs = 1;
        $this->cookie_jar = array();
    }

    function post ($url,$data) {
        //echo 'POST: '.$url."\n";
        $time = microtime(1);
        curl_setopt($this->ch,CURLOPT_URL,$url);
        curl_setopt($this->ch,CURLOPT_USERAGENT,'php wikibot classes');
        $cookies = null;
        foreach ($this->cookie_jar as $name => $value) {
            if (empty($cookies))
                $cookies = "$name=$value";
            else
                $cookies .= "; $name=$value";
        }
        if ($cookies != null)
            curl_setopt($this->ch,CURLOPT_COOKIE,$cookies);
        curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->postfollowredirs);
        curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
        curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
        curl_setopt($this->ch,CURLOPT_POST,1);
//      curl_setopt($this->ch,CURLOPT_FAILONERROR,1);
//	curl_setopt($this->ch,CURLOPT_POSTFIELDS, substr($this->data_encode($data), 0, -1) );
        curl_setopt($this->ch,CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($this->ch);
//	echo "Error: ".curl_error($this->ch);
//	var_dump($data);
//	global $logfd;
//	if (!is_resource($logfd)) {
//		$logfd = fopen('php://stderr','w');
	if (!$this->quiet)
            echo 'POST: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
// 	}
        return $data;
    }

    function get ($url) {
        //echo 'GET: '.$url."\n";
        $time = microtime(1);
        curl_setopt($this->ch,CURLOPT_URL,$url);
        curl_setopt($this->ch,CURLOPT_USERAGENT,'php wikibot classes');
        $cookies = null;
        foreach ($this->cookie_jar as $name => $value) {
            if (empty($cookies))
                $cookies = "$name=$value";
            else
                $cookies .= "; $name=$value";
        }
        if ($cookies != null)
            curl_setopt($this->ch,CURLOPT_COOKIE,$cookies);
        curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->getfollowredirs);
        curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
        curl_setopt($this->ch,CURLOPT_HEADER,0);
        curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
        curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
        curl_setopt($this->ch,CURLOPT_HTTPGET,1);
        //curl_setopt($this->ch,CURLOPT_FAILONERROR,1);
        $data = curl_exec($this->ch);
        //echo "Error: ".curl_error($this->ch);
        //var_dump($data);
        //global $logfd;
        //if (!is_resource($logfd)) {
        //    $logfd = fopen('php://stderr','w');
        if (!$this->quiet)
            echo 'GET: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
        //}
        return $data;
    }

    function setHTTPcreds($uname,$pwd) {
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_USERPWD, $uname.":".$pwd);
    }

    function __destruct () {
        curl_close($this->ch);
        @unlink('/tmp/wikibot.cookies.'.$this->uid.'.dat');
    }
}

/**
 * Esta api interactua con una wiki usando api.php
 * @author Emilio D. Tejedor
 **/

class bot {
    private $http;
    private $token;
    private $ecTimestamp;
    public $url;

    /**
     * Este es el constructor.
     * @return void
     **/
    
    function __construct ($url = 'http://es.wikipedia.org/w/api.php', $hu = null, $hp = null) {
        $this->http = new http;
        $this->token = null;
        $this->url = $url;
        $this->ecTimestamp = null;
        if ($hu!==null)
        	$this->http->setHTTPcreds($hu,$hp);
    }

    function __set($var, $val) {
	switch($var) {
  		case 'quiet':
			$this->http->quiet=$val;
     			break;
   		default:
     			echo "WARNING: Unknown variable ($var)!\n";
 	}
    }

    /**
     * Envía una petición a la api
     * @param $query La cadena de la petición
     * @param $post Postea los datos si es una petición post (opcional)
     * @return El resultado de la api
     **/
    
    function query ($query, $post = null, $repeat = 0) {
        if ($post==null) {
            $ret = $this->http->get($this->url.$query);
        } else {
            $ret = $this->http->post($this->url.$query,$post);
        }
		if ($this->http->http_code() != "200") {
			if ($repeat < 10) {
				return $this->query($query,$post,++$repeat);
			} else {
				throw new Exception("HTTP Error.");
			}
		}
        return unserialize($ret);
    }

    /**
     * Obtiene el contenido de una página. Devuelve false en caso de error
     * @param $page La página a mostrar
     * @param $revid El id de la revisión a mostrar (opcional)
     * @return El wikicodigo de la página
     **/
    
    function getpage ($page, $revid = null, $detectEditConflict = false) {
        $append = '';
        if ($revid != null) {
            $append = '&rvstartid=' . $revid;
        }
        $x = $this->query('?action=query&format=php&prop=revisions&titles=' . urlencode($page) . '&rvlimit=1&rvprop=content|timestamp' . $append);
        foreach ($x['query']['pages'] as $ret) {
            if (isset($ret['revisions'][0]['*'])) {
                if ($detectEditConflict) {
                    $this->ecTimestamp = $ret['revisions'][0]['timestamp'];
                }
                return $ret['revisions'][0]['*'];
            } else
                return false;
        }
    }

    /**
     * Obtiene el id de una página
     * @param $page La página a obtener id
     * @return El id de la página
     **/
    
    function getpageid ($page) {
        $x = $this->query('?action=query&format=php&prop=revisions&titles=' . urlencode($page) . '&rvlimit=1&rvprop=content');
        foreach ($x['query']['pages'] as $ret) {
            return $ret['pageid'];
        }
    }

    /**
     * Devuelve el número de ediciones de un usuario
     * @param $user El nombre de usuario a obtener su número de ediciones
     * @return El número de ediciones que tiene el usuario
     **/
    
    function editcount ($user) {
        $x = $this->query('?action=query&list=users&format=php&usprop=editcount&ususers=' . urlencode($user));
        return $x['query']['users'][0]['editcount'];
    }

    /**
     * Devuelve un array con los miembros de $category
     * @param $category La categoría a usar.
     * @param $subcat (bool) Contar las subcategorías
     * @return array
     **/
    
    function categorymembers ($category, $subcat = false) {
        $continue = '';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=categorymembers&cmtitle=' . urlencode($category) . '&format=php&cmlimit=500' . $continue);
            if (isset($x['error'])) {
                return false;
            }
            foreach ($res['query']['categorymembers'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['categorymembers']['cmcontinue'])) {
                if ($subcat) {
                    foreach ($pages as $p) {
                        if (substr($p,0,9) == 'Category:') {
                            $pages2 = $this->categorymembers($p,true);
                            $pages = array_merge($pages,$pages2);
                        }
                    }
                }
                return $pages;
            } else {
                $continue = '&cmcontinue='.urlencode($res['query-continue']['categorymembers']['cmcontinue']);
            }
        }
    }
    
    /**
     * Devuelve un array con todas las páginas de la wiki
     * @param $from Página a empezar a numerar (desde el principio, por defecto)
     * @param $ns nombre de espacio (principal, por defecto)
     * @return array
     **/
    
    function allpages ($from = '', $ns = '0') {
	$continue = '';
	$pages = array();
	while (true) {
	    $res = $this->query('?action=query&list=allpages&format=php&apnamespace=' . $ns . '&aplimit=500' . $continue . '&apfrom=' . urlencode($from));
	    if (isset($x['error'])) {
                return false;
            }
	    foreach ($res['query']['allpages'] as $x) {
		$pages[] = $x['title'];
	    }
	    if (empty($res['query-continue']['allpages']['apcontinue'])) {
		return $pages;
	    } else {
		$continue = '&apcontinue=' . urlencode($res['query-continue']['allpages']['apcontinue']);
	    }
	}
    }

    /**
     * Devuelve una lista de las páginas que enlazan a $page
     * @param $page
     * @param $ns nombre de espacio (principal, por defecto)
     * @return array
     **/
    
    function whatlinkshere ($page, $ns = '0') {
        $continue = '';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=backlinks&bltitle=' . urlencode($page) . '&bllimit=500&blnamespace=' . $ns . '&format=php' . $continue . $extra);
            if (isset($res['error'])) {
                return false;
            }
            foreach ($res['query']['backlinks'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['backlinks']['blcontinue'])) {
                return $pages;
            } else {
                $continue = '&blcontinue='.urlencode($res['query-continue']['backlinks']['blcontinue']);
            }
        }
    }
    
    /**
     * Devuelve un array con las subpáginas de $page
     * @param $page
     * @return array
     **/
    
    function subpages ($page) {
        /* Calcula los códigos del espacio de nombre */
        $ret = $this->query('?action=query&meta=siteinfo&siprop=namespaces&format=php');
        foreach ($ret['query']['namespaces'] as $x) {
            $namespaces[$x['*']] = $x['id'];
        }
        $temp = explode(':',$page,2);
        $namespace = $namespaces[$temp[0]];
        $title = $temp[1];
        $continue = '';
        $subpages = array();
        while (true) {
            $res = $this->query('?action=query&format=php&list=allpages&apprefix=' . urlencode($title) . '&aplimit=500&apnamespace=' . $namespace . $continue);
            if (isset($x['error'])) {
                return false;
            }
            foreach ($res['query']['allpages'] as $p) {
                $subpages[] = $p['title'];
            }
            if (empty($res['query-continue']['allpages']['apfrom'])) {
                return $subpages;
            } else {
                $continue = '&apfrom=' . urlencode($res['query-continue']['allpages']['apfrom']);
            }
        }
    }

    /**
     * Esta función es para iniciar sesión con un usuario y contraseña
     * @param $user Nombre de usuario a utilizar
     * @param $pass La contraseña que corresponde al usuario
     * @return array
     **/
    
    function login ($user, $pass) {
    	$post = array('lgname' => $user, 'lgpassword' => $pass);
        $ret = $this->query('?action=login&format=php', $post);
        if ($ret['login']['result'] == 'NeedToken') {
        	$post['lgtoken'] = $ret['login']['token'];
        	$ret = $this->query('?action=login&format=php', $post);
        }
        if ($ret['login']['result'] != 'Success') {
            echo "Login error: \n";
            print_r($ret);
            die();
        } else {
            return $ret;
        }
    }

    /* Guarda cookies del inicio de sesión */
    
    function setLogin($data) {
        $this->http->cookie_jar = array(
        $data['cookieprefix'].'UserName' => $data['lgusername'],
        $data['cookieprefix'].'UserID' => $data['lguserid'],
        $data['cookieprefix'].'Token' => $data['lgtoken'],
        $data['cookieprefix'].'_session' => $data['sessionid'],
        );
    }

    /**
     * Devuelve un edit token para el usuario actual.
     * @param Página a obtener el edit token.
     * @return edit token.
     **/
    
    function getedittoken ($page) {
        $x = $this->query('?action=query&prop=info&intoken=edit&titles=' . urlencode($page) . '&format=php');
        foreach ($x['query']['pages'] as $ret) {
            return $ret['edittoken'];
        }
    }

    /**
     * Edita una página
     * @param $page Nombre de la página a editar
     * @param $data Datos a guardar en la página
     * @param $summary Resumen de edición a mostrar
     * @param $minor Marca una edición como menor (false por defecto)
     * @param $bot Marca una edición como bot (true por defecto)
     * @param $section 0 para la sección de hasta arriba, new para una nueva sección
     * @return resultado de la api
     **/
    
    function edit ($page, $data, $summary = '', $minor = false, $bot = true, $section = null, $detectEC = false, $maxlag = '') {
        if ($this->token == null) {
            $this->token = $this->getedittoken($page);
        }
        $params = array(
            'title' => $page,
            'text' => $data,
            'token' => $this->token,
            'summary' => $summary,
            ($minor?'minor':'notminor') => '1',
            ($bot?'bot':'notbot') => '1'
        );
        if ($section != null) {
            $params['section'] = $section;
        }
        if ($this->ecTimestamp != null && $detectEC == true) {
            $params['basetimestamp'] = $this->ecTimestamp;
            $this->ecTimestamp = null;
        }
        if ($maxlag != '') {
            $maxlag='&maxlag=' . $maxlag;
        }
        return $this->query('?action=edit&format=php' . $maxlag, $params);
    }
}
?>