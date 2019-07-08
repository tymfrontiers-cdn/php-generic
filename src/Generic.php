<?php
namespace TymFrontiers;

\defined('HTTP_BAD_REQUEST') ? NULL : \define('HTTP_BAD_REQUEST',400);
\defined('HTTP_UNAUTHORIZED') ? NULL : \define('HTTP_UNAUTHORIZED',401);
\defined('HTTP_FORBIDDEN') ? NULL : \define('HTTP_FORBIDDEN',403);
\defined('HTTP_NOT_FOUND') ? NULL : \define('HTTP_NOT_FOUND',404);
\defined('HTTP_INTERNAL_ERROR') ? NULL : \define('HTTP_INTERNAL_ERROR',500);

class Generic{
  public $errors = [];

  public static function redirect(string $location){
    \header('Location: '.$location);
    exit;
  }
  public static function setGet(string $url, array $param_val){
    \parse_str( \parse_url($url, PHP_URL_QUERY), $query);
    // var_dump($query);
    foreach($param_val as $k=>$v){
      $query[$k] = $v;
    }
    return \explode('?',$url)[0] . "?" . \http_build_query($query);
  }
  public static function allowedParam(array $params, $method='get'){
    // global
    $method = \is_array($method) ? $method : \strtolower($method);
    $methods=['get'=>$_GET,'post'=>$_POST];
    $allowed = [];
    $request = \is_array($method) ? $method : $methods[$method];
    foreach( $params as $ak ){ $allowed[$ak] = NULL; }
    if( !empty($request) ){
      foreach($params as $param){
        if( isset($request[$param]) ){
          $allowed[$param] = \gettype($request[$param]) == "string" ? \trim($request[$param]) : $request[$param];
        }
      }
    }
    return $allowed;
  }
  public static function splitEmailName(string $string){
    $return = [ 'name'=>'','email'=>'' ];

    if( \strpos($string, '<') !== false && \strpos($string, '>') !== false ){
      $name =  \filter_var($string,FILTER_SANITIZE_STRING);
      $email = \filter_var( \str_replace($name,'',$string),FILTER_SANITIZE_EMAIL);
      $return['name'] = !empty($name) ? $name : '';
      $return['email'] = \filter_var($email,FILTER_VALIDATE_EMAIL) ? $email : '';
    }else{
      $email = \filter_var($string,FILTER_SANITIZE_EMAIL);
      $return['email'] = $email ? $email : '';
    }

    return $return;
  }
  public static function fileExt(string $filename) {
    $qpos = \strpos($filename, "?");
    if ( $qpos!==false ) $filename = \substr($filename, 0, $qpos);
    $extension = \pathinfo($filename, PATHINFO_EXTENSION);
    return $extension;
  }
  public static function urlExist(string $url){
      $ch = \curl_init($url);
      \curl_setopt($ch, CURLOPT_NOBODY, true);
      \curl_exec($ch);
      $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if($code == 200){
         $status = true;
      }else{
        $status = false;
      }
      curl_close($ch);
     return $status;
  }

  public function requestParam(array $columns, $method, array $required, bool $strict=false){
    $params_errors = [];
    $params = self::allowedParam( \array_keys($columns),$method );
    // check required
    $req_errors = 0;
    foreach ($required as $key) {
      if( $columns[$key][1] !== "boolean" ){
        if( empty($params[$key])  ){
          $req_errors ++;
          $this->errors['requestParam'][] = [0,256,"[{$key}]: is required but not present with request.",__FILE__,__LINE__];
        }
      }else{
        if( $params[$key] == '' ){
          $req_errors ++;
          $this->errors['requestParam'][] = [0,256,"[{$key}]: is required but not present with request.",__FILE__,__LINE__];
        }
      }
    }
    $validator = new Validator();
    // check values
    foreach ($params as $key => $value) {
      if( $columns[$key][1] !== "boolean" ){
        $value = $columns[$key][1] == 'int'
          ? (int)$value
          : ($columns[$key][1] == 'float'
              ? (float)$value
              : $value
            );
        if( !empty($value) ){
          if( !$params[$key] = $validator->validate($value,$columns[$key])  ){
            $err = (new InstanceError($validator))->get($columns[$key][1],true);
            if( !empty($err) ){
              foreach ($err as $er) {
                $this->errors['requestParam'][] = [0,256,$er,__FILE__,__LINE__];
                if( isset( $validator->errors[$columns[$key][1]] ) ) unset( $validator->errors[$columns[$key][1]] );
              }
            }
          }
        }
      }else{
        if( $value !== '' ){
          $params[$key] = (bool)$value;
        }
      }
      // if( \in_array($key,$required) ){
      //
      //   if( !$params[$key] = $validator->validate($value,$columns[$key])  ){
      //     $err = (new InstanceError($validator))->get($columns[$key][1],true);
      //     if( !empty($err) ){
      //       foreach ($err as $er) {
      //         $this->errors['requestParam'][] = [0,256,$er,__FILE__,__LINE__];
      //         if( isset( $validator->errors[$columns[$key][1]] ) ) unset( $validator->errors[$columns[$key][1]] );
      //       }
      //     }
      //   }
      // }else{
      //   if( !empty($value) ){
      //     if( !$params[$key] = $validator->validate($value,$columns[$key])  ){
      //       $err = (new InstanceError($validator))->get($columns[$key][1],true);
      //       if( !empty($err) ){
      //         foreach ($err as $er) {
      //           $this->errors['requestParam'][] = [0,256,$er,__FILE__,__LINE__];
      //           if( isset( $validator->errors[$columns[$key][1]] ) ) unset( $validator->errors[$columns[$key][1]] );
      //         }
      //       }
      //     }
      //   }
      // }
    }
    return (bool)$strict ? (
      empty( $this->errors['requestParam'] ) ? $params : false
    ) : (
      empty( $this->errors['requestParam'] ) || $req_errors <= 0 ? $params : false
    );
  }
  public static function patternReplace(array $pattern, array $replace, string $value){
    foreach($pattern as $key=>$pattern){
      if( \array_key_exists($key,$replace) ){
        $value = \str_replace($pattern,$replace[$key],$value);
      }
    }
    return $value;
  }

  public function checkAccess( string $page ){
    global $database, $session;
    $errors = [];
    // die( var_dump($session) );
    if( !($database instanceof MySQLDatabase) ){
      $this->errors["checkAccess"][] = [3,256,'There must be an instance of TymFrontiers\MySQLDatabase in the name of \'$database\' on global scope',__FILE__,__LINE__];
      $this->errors['checkAccess'][] = [0,256,"Runtym config error, kindly contact admin/developer.",__FILE__,__LINE__];
      return false;
    } if( !($session instanceof Session) ){
      $errors[] = [3,256,'There must be an instance of TymFrontiers\Session in the name of \'$session\' on global scope',__FILE__,__LINE__];
      $this->errors['checkAccess'][] = [0,256,"Runtym config error, kindly contact admin/developer.",__FILE__,__LINE__];
      return false;
    } if( !$session->isLoggedIn() ){
      $this->errors['checkAccess'][] = [0,256,"You must be logged in to proceed with request.",__FILE__,__LINE__];
      // $this->errors['checkAccess'][] = [0,256,"Runtym config error, kindly contact admin/developer.",__FILE__,__LINE__];
      return false;
    } if( ! \defined('ADMIN_DB') ){
      $this->errors['checkAccess'][] = [3,256,"ADMIN_DB (admin database name) not defined.",__FILE__,__LINE__];
      $this->errors['checkAccess'][] = [0,256,"Runtym config error, kindly contact admin/developer.",__FILE__,__LINE__];
      return false;
    }
    $page = $database->escapeValue($page);
    $page_access = $database->fetchAssocArray($database->query("SELECT * FROM " . ADMIN_DB . ".page_access WHERE name='{$page}' LIMIT 1"));

  	$page_access = $page_access ? $page_access : false;
  	if( !$page_access ){
  		$this->errors['checkAccess'][] = [0,256,"Undefined page access, contact admin.",__FILE__,__LINE__];
  	}
    $groups = \explode(',',$page_access['groups']);
    if( !\in_array($session->user->ugroup,$groups) || $session->admin_rank < $page_access['min_rank']){
      $this->errors['checkAccess'][] = [0,256,"Admin group/level/rank conflicts with page access group/rank, contact admin.",__FILE__,__LINE__];
    }
    return empty( $this->errors['checkAccess'] );
  }
  public function checkCSRF( string $form, string $token ){
    global $session;
    $errors = [];
    if( !($session instanceof Session) ){
      $this->errors['checkCSRF'][] = [3,256,'There must be an instance of TymFrontiers\Session in the name of \'$session\' on global scope',__FILE__,__LINE__];
    }

    if( @ !$sess_token = $_SESSION['CSRF_token'][$form] ){
      $this->errors['checkCSRF'][] = [0,256,"No request/form-matching security token in storage. \r\n This might be an unauthorized, or timed out request. \r\n You may reload web page and try again or contact admin if error persists.",__FILE__,__LINE__];
      return empty( $this->errors['checkCSRF'] );
    }
    $token_expire = \explode('::',$sess_token)[1];
    $sess_token = \explode('::',$sess_token)[0];
    if( \time() > (int)$token_expire ){
      unset($_SESSION['CSRF_token'][$form]);
      $this->errors['checkCSRF'][] = [0,256,"Validation token expired, please reload page and try again..",__FILE__,__LINE__];
      return empty( $this->errors['checkCSRF'] );
    } if( $token !== $sess_token ){
      $this->errors['checkCSRF'][] = [0,256,"Request security validation failed, please reload page or contact admin.",__FILE__,__LINE__];
    }
    return empty( $this->errors['checkCSRF'] );
  }
  public static function isBase64(string $data){
    return \preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data);
  }

}
