<?php
/* @author: vttai96@gmail.com
 * @version: 1.0 */

error_reporting(E_ERROR);
class SynoFileHostingFsharedotVN
{
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;
    private $FSHAREVN_COOKIE = '/tmp/fsharevn.cookie';
    private $FSHAREVN_TOKEN = '/tmp/fsharevn.token';
    private $FSHAREVN_LIST = '/tmp/fsharevn.txt';
    private $FSHAREVN_LOG = '/tmp/fsharevn.log';
    private $FSHAREVN_API = 'https://api.fshare.vn';
    private $FSHAREVN_URL = 'https://www.fshare.vn/';
    private $app_key = 'dMnqMMZMUnN5YpvKENaEhdQQ5jxDqddt';
    private $user_agent = 'FshareVN-DMXA3T';
    
    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url      = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;
        $this->logInfo("Url: " . $Url);
        $this->logInfo("HostInfo: ". print_r($HostInfo, true));
    }
    
    //This function returns download url.
    public function GetDownloadInfo()
    {
        $this->logInfo(" . . . ");
        $this->logInfo("GetDownloadInfo ... ");
        $ret       = false;
        $VerifyRet = $this->Verify(false);
        if (LOGIN_FAIL == $VerifyRet) {
            $ret[DOWNLOAD_ERROR] = LOGIN_FAIL;
            $this->logError("LOGIN_FAIL");
        } else if (USER_IS_FREE == $VerifyRet) {
            $ret[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
            $this->logError("ERR_REQUIRED_PREMIUM");
        } else {
            $ret = $this->GetDownloadLink($this->TokenValue);
        }
        $this->logInfo("Return : " . print_r($ret, true));
        return $ret;
    }
    
    //This function verifies and returns account type.
    public function Verify($ClearCookie)
    {
        $this->logInfo("Verify ... ");
        $ret = LOGIN_FAIL;
        $this->TokenValue = $this->getToken();
        if (false != $this->TokenValue && file_exists($this->FSHAREVN_COOKIE)) {
            //Check Account
            $ret = $this->IsFreeAccount(); 
        }
        if (LOGIN_FAIL != $ret) {
            goto End;
        }
        if (!empty($this->Username) && !empty($this->Password)) {
            $this->TokenValue = $this->FshareVNLogin($this->Username, $this->Password);
        }
        if (false == $this->TokenValue) {
            goto End;
        }
        $ret = $this->IsFreeAccount();
        
End:
        if ($ClearCookie && file_exists($this->FSHAREVN_TOKEN)) {
            unlink($this->FSHAREVN_TOKEN);
            $this->logError("Clear Token");
        }
        if ($ClearCookie && file_exists($this->FSHAREVN_COOKIE)) {
            unlink($this->FSHAREVN_COOKIE);
            $this->logError("Clear Cookie");
        }
        return $ret;
    }
    
    private function FshareVNLogin($Username, $Password)
    {
        $this->logInfo("FshareVNLogin ... ");
        $ret = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/user/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array(
            "user_email" => $Username,
            "password" => $Password,
            "app_key" => $this->app_key
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        //Save cookie file
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->FSHAREVN_COOKIE);
        $headers   = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $LoginInfo = curl_exec($ch);
        $httpcode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (false != $LoginInfo && file_exists($this->FSHAREVN_COOKIE)) {
            if (200 == $httpcode) {
                $result = json_decode($LoginInfo, true);
                $ret    = $result['token'];
                $this->saveToken($ret);
            }
            else if (403 == $httpcode) {
                // free account
                $ret = true;
            }
        }
        return $ret;
    }
    
    private function IsFreeAccount()
    {
        $this->logInfo("IsFreeAccount ... ");
        $ret = LOGIN_FAIL;
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/user/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->FSHAREVN_COOKIE);
        $headers   = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $AccountRet = curl_exec($ch);
        $httpcode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (false != $AccountRet) {
            if (200 == $httpcode) {
                if ('Vip' == json_decode($AccountRet)->{'account_type'}) {
                    $ret = USER_IS_PREMIUM;
                } else {
                    $ret = USER_IS_FREE;
                }
            }
        }
        return $ret;
    }
    
    private function GetDownloadLink($TokenValue) {
        $this->logInfo("GetDownloadLink ... ");
        $splitUrl = preg_split('/[,;|]/x', $this->Url . ' , ,');
        $this->realPwd = trim($splitUrl[1]);
        $this->opt = trim($splitUrl[2]);
        if (strpos($splitUrl[0], 'folder')) {
            return $this->GetLinkFolder($TokenValue, $splitUrl[0]);
        } else {
            return $this->GetLinkFile($TokenValue, $splitUrl[0]);
        }
    }

    private function GetLinkFolder($TokenValue, $Url) {
        $this->logInfo("GetLinkFolder ... ");
        $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
        $myfile = fopen($this->FSHAREVN_LIST, "w");
        $createFile = $this->GetFolder($TokenValue, $Url, $myfile);
        if (false != $createFile) {
            $fileUrl = $this->UploadFile($TokenValue);
            if (false != $fileUrl) {
                $DownloadInfo = $this->GetLinkFile($TokenValue, $fileUrl);
            }
        }
        fclose($myfile);
        return $DownloadInfo;
    }

    private function UploadFile($TokenValue) {
        $this->logInfo("UploadFile ... ");
        $downloadUrl = false;
        $upload_link = $this->UploadStep1($TokenValue);
        if (false != $upload_link) {
            $downloadUrl = $this->UploadStep2($upload_link);
            $this->logInfo("Upload link: " . $downloadUrl);
        }
        return $downloadUrl;
    }

    private function UploadStep1($TokenValue) {
        $this->logInfo("UploadStep1 ... ");
        $ret = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/session/upload');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array(
            "name" => "FsharedotVN_" . date('Y-m-d_H-i-s') . ".txt",
            "size" => strval(filesize($this->FSHAREVN_LIST)),
            "path" => "/",
            "token" => $TokenValue,
            "secured" => 1
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->FSHAREVN_COOKIE); 
        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        $headers[] = 'Content-Type: application/json; charset=UTF-8';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (false != $result) {
            if (200 == $httpcode) {
                $ret = json_decode($result)->{"location"};
            }
        }
        return $ret;
    }

    private function UploadStep2($upload_link) {
        $this->logInfo("UploadStep2 ... ");
        $handle = fopen($this->FSHAREVN_LIST, "r");
        $contents = fread($handle, filesize($this->FSHAREVN_LIST));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($handle);
        $this->logInfo("Upload respone: " . print_r($result, true));
        if (false != $result) {
            if (200 == $httpcode) {
                $ret = json_decode($result)->{"url"};
            }
        }
        return $ret;
    }

    private function GetFolder($TokenValue, $Url, $myfile) {
        $this->logInfo("GetFolder ... ");
        $ret =  false;
        preg_match('/folder\/\w*/', $Url, $localUrl);
        $localUrl = $localUrl[0];
        $total = $this->GetFolderTotal($TokenValue, $localUrl);
        if (0 != $total) {
            $this->logInfo("numPage: " . $total/60);
            for ($i = 0; $i < $total/60; $i++) {
                $pageParse = $this->GetFolderList($TokenValue, $localUrl, $i); 
                if (false != $pageParse) {
                    foreach ($pageParse as $value) {
                        if (1 == intval($this->opt)) {
                            if (strpos($value["furl"], 'folder')) {
                                $str = "--- " . $value["name"] . "\r\n";
                                fwrite($myfile, $str);
                                $this->logInfo("GetFolder Inside");
                                $this->GetFolder($TokenValue, $value["furl"], $myfile);
                                $str = "--- \r\n";
                                fwrite($myfile, $str);
                            } else {
                                $str = $value["furl"] . " , " . $this->realPwd . " , " . $value["name"] . "\r\n";
                                fwrite($myfile, $str);
                            }
                        } else {
                            $str = $value["furl"] . " , " . $this->realPwd . " , " . $value["name"] . "\r\n";
                            fwrite($myfile, $str);
                        }
                    }   
                }       
            }
            $ret =  true;
        }
        return $ret;
    }

    private function GetFolderList($TokenValue, $Urlcut, $pageIndex) {
        $this->logInfo("GetFolderList ... ");
        $ret = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/fileops/getFolderList');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array(
            "url" => $this->FSHAREVN_URL . $Urlcut,
            "dirOnly" => 0,
            "pageIndex" => $pageIndex,
            "limit" => 60,
            "token" => $TokenValue
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->FSHAREVN_COOKIE);  

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $FolderList = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (false != $FolderList) {
            if (200 == $httpcode) {
                $ret = json_decode($FolderList, true);
            }
        }
        return $ret;
    }

    private function GetFolderTotal($TokenValue, $Urlcut) {
        $this->logInfo("GetFolderTotal ... ");
        $ret = 0;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/fileops/getTotalFileInFolder');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array(
            "token" => $TokenValue,
            "url" => $this->FSHAREVN_URL . $Urlcut,
            "have_file" => false
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->FSHAREVN_COOKIE);  

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $FolderTotal = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (false != $FolderTotal) {
            if (200 == $httpcode) {
                $ret = json_decode($FolderTotal)->{'total'};
                // $ret = 0;
            }
        }
        return intval($ret);
    }

    //This function get premium download url.
    private function GetLinkFile($TokenValue, $Url)
    {
        $this->logInfo("GetLinkFile ... ");
        $DownloadInfo = false;
        if (!strpos($Url, 'file')) {
            $this->realUrl .= 'file/'; 
        }
        preg_match('/file\/\w*/', $Url, $this->realUrl);
        $this->realUrl = $this->realUrl[0];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->FSHAREVN_API . '/api/session/download');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array(
            "url" => $this->FSHAREVN_URL . $this->realUrl,
            "password" => $this->realPwd,
            "token" => $TokenValue,
            "zipflag" => '0'
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->FSHAREVN_COOKIE);    
        $headers   = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: ' . $this->user_agent;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $DownloadUrl   = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->logInfo("Post data: " . print_r($data, true));
        $this->logInfo("Response: " . print_r($DownloadUrl, true));
        curl_close($ch);
        if (false != $DownloadUrl) {
            if (200 == $httpcode) {
                $returl = json_decode($DownloadUrl)->{'location'};
                $DownloadInfo               = array();
                $DownloadInfo[DOWNLOAD_URL] = trim($returl);
            } else if (400 == $httpcode || 201 == $httpcode) {
                // need login
                $this->logError("NEED LOGIN");
            }
        }
        else {
            // $DownloadInfo[DOWNLOAD_ERROR] = "Undefined Error";
            $DownloadInfo[DOWNLOAD_ERROR] = ERR_NOT_SUPPORT_TYPE;
            $this->logError("Undefined Error");
        }
        return $DownloadInfo;
    }

    private function saveToken($token) {
        $this->logInfo("saveToken ... ");
        $myfile = fopen($this->FSHAREVN_TOKEN, "w");
        fwrite($myfile, $token);
        fclose($myfile);
    }

    private function getToken() {
        $this->logInfo("getToken ... ");
        $ret = false;
        if(file_exists($this->FSHAREVN_TOKEN)) {
            $myfile = fopen($this->FSHAREVN_TOKEN, "r");
            $ret = fgets($myfile);
            fclose($myfile);
        }
        return $ret;
    }
    private function logError($msg) {
        $this->log("[ERROR]", $msg);
    }

    private function logInfo($msg) {
        $this->log("[INFO]", $msg);
    }

    private function log($prefix, $msg) {
        error_log($prefix . " - " . date('Y-m-d H:i:s') . " - " . $msg . "\r\n", 3, $this->FSHAREVN_LOG);
    }
}
?>