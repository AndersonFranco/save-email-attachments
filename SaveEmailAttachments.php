<?php
/**
 * Project: Save Email Attachments
 * File:    SaveEmailAttachments.php
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2013 Franco Tecnologia (http://francotecnologia.com)
 * @author    Anderson Franco <anderson@francotecnologia.com>
 * @version   1.0 (14 Oct 2013)
 */

/**
 * Save attachments from e-mail messages
 */
class SaveEmailAttachments {

  protected $mailbox  = ''; // e.g. "{imap.gmail.com:993/imap/ssl}INBOX";
  protected $username = ''; // e.g. username@gmail.com
  protected $password = ''; // e.g. 12345

  /**
   * Relative path to a nested directory of this file
   *
   * Trailing slash is mandatory ('/' at the end)
   */
  protected $filesPath = 'files/';
  protected $dataPath  = 'data/';

  /**
   * Constructor (mailbox, username, password)
   */
  public function SaveEmailAttachments($m = null, $u = null, $p = null) {

    if ($m !== null) $this->mailbox  = $m;
    if ($u !== null) $this->username = $u;
    if ($p !== null) $this->password = $p;

    if (!function_exists('imap_open')) {
      throw new Exception("IMAP module not available.", 1);     
    }

    if (!file_exists($this->dataPath)) {
      if (!mkdir($this->dataPath, 0755, true)) {
        throw new Exception("Unable to create {$this->dataPath} folder.", 1);
      }
    }   
  }

  /**
   * Decode message
   */
  protected function getdecodevalue($message, $coding) {
    switch($coding) {
      case 0:
      case 1:
        $message = imap_8bit($message);
        break;
      case 2:
        $message = imap_binary($message);
        break;
      case 3:
      case 5:
      case 6:
      case 7:
        $message=imap_base64($message);
        break;
      case 4:
        $message = imap_qprint($message);
        break;
    }
    return $message;
  }

  /**
   * Recursively removes directory
   */
  protected static function delTree($dir) {
   $dir   = './' . $dir;
   $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      (is_dir("$dir/$file") && !is_link($dir)) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
  }

  /**
   * Process new messages and save files
   */
  public function run($verbose = null) {

    $mbox = imap_open($this->mailbox, $this->username, $this->password);
    if (!$mbox) {
      throw new Exception("Connection failed: " . imap_last_error(), 1);
    }
     
    $MC            = imap_check($mbox);
    $result        = imap_fetch_overview($mbox, "1:{$MC->Nmsgs}", 0);
    $emailMessages = 0;

    /**
     * Process each e-mail message
     */
    foreach ($result as $overview) {

      /**
       * Skip old messages
       */
      if ($overview->seen) continue;

      $files  = 0;
      $errors = 0;
      $emailMessages++;

      /**
       * Create a folder for each message
       *
       * If already exists, set message as Seen
       */
      $folder = (int) $overview->uid;
      if ($folder > 0) {
        if (file_exists($this->filesPath . $folder)) {
          if ($verbose) {
            echo "#{$overview->uid} already downloaded." . PHP_EOL;
          }
          imap_setflag_full($mbox, $overview->msgno, "\\Seen");
          continue;
        }
        if (!mkdir($this->filesPath . $folder, 0755, true)) {
          throw new Exception('Unable to create new folder: ' 
                              . $this->filesPath . $folder, 1);
        }
      } else {
        continue;
      }

      /**
       * Save attachments
       */
      $structure = imap_fetchstructure($mbox, $overview->msgno);
      $parts     = isset($structure->parts) ? $structure->parts : 0;
      $fpos      = 2;

      for($i = 1; $i < count($parts); $i++) {
        $part = $parts[$i];
        if (isset($part->disposition) && strtolower($part->disposition) == "attachment") {
          $ext      = $part->subtype;
          $params   = $part->dparameters;
          $fileName = $part->dparameters[0]->value;
          $mbody    = imap_fetchbody($mbox, $overview->msgno, $fpos);
          $content  = $this->getdecodevalue($mbody, $part->type);
          $saveAs   = preg_replace("/[^a-z0-9._]/", "", str_replace(" ", "_", str_replace("%20", "_", strtolower(basename($fileName)))));

          if ($saveAs != '') {
            $fp = fopen($this->filesPath . $folder . '/' . $saveAs, 'w');
            fputs($fp, $content);
            fclose($fp);
          } else {
            $errors++;
          }

          $fpos++;
          $files++;
        }
      }

      /**
       * On success save data from e-mail
       *
       * Otherwise, delete files and set message to Unread
       */
      if ($files > 0 && $errors == 0) {
        file_put_contents($this->dataPath . $folder . '.json', json_encode(array(
            'uid'     => $overview->uid,
            'from'    => $overview->from,
            'subject' => $overview->subject
          )), LOCK_EX);
      } else {
        if ($verbose) {
          echo "Skipping ";
        }
        imap_clearflag_full($mbox, $overview->msgno, "\\Seen");
        $this->delTree($this->filesPath . $folder);
      }
      if ($verbose) {
        echo "#{$overview->uid} "
          . htmlentities($overview->from, ENT_COMPAT, "UTF-8")
          . " {$overview->subject}" . PHP_EOL;
       }

      /**
       * On error, print message if verbose = true
       */
      if ($errors > 0) {
        if ($verbose) {
          echo "Error on processing e-mail #{$overview->uid}." . PHP_EOL;
        }
      }

    }

    imap_close($mbox);
    return $emailMessages;
  }

  /*
   * Print saved files
   */
  public function printFiles($html = null) {

    $email = glob($this->dataPath . '*.json');

    foreach($email AS $e) {
      $ema = json_decode(file_get_contents($e));

      echo "#{$ema->uid} "
            .  htmlentities($ema->from, ENT_COMPAT, "UTF-8")
            .  " {$ema->subject}" . PHP_EOL;

      $anexos = glob($this->filesPath . (int) $ema->uid . '/*.*');
      foreach ($anexos AS $anexo) {
        echo "- ", ($html) ? "<a target=\"_blank\" href=\"" . $anexo . "\">" : '',
            basename($anexo), ($html) ? "</a>" : '', PHP_EOL;
      }
      echo PHP_EOL;
    }

  }

}
