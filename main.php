<?php
/**
 * Project: Save Email Attachments
 * File:    index.php
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

  error_reporting(0);
  set_time_limit(0);

  require 'SaveEmailAttachments.php';

  /**
   * Detect CLI mode
   */
  function is_cli() {
    if(defined('STDIN')) {return true;} else 
    if(empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT'])
    && count($_SERVER['argv'])>0) {return true;} return false;}

  /**
   * Send headers and html if running through the browser
   */
  if (!is_cli()) {
    header('Content-type: text/html; charset=utf-8');
    echo "<html><head><style>*{font-family: consolas, courier, monospace; font-size: 1em; line-height: 1.2em; white-space: pre;}</style><body>";
  }

  /**
   * Process new messages and print saved files
   */
  try {

    $email = new SaveEmailAttachments(
      /**
       * Mailbox
       */
      '{imap.gmail.com:993/imap/ssl}INBOX',
      /**
       * Username / Account / Email
       */
      'user@gmail.com',
      /**
       * Password
       */
      '12345'
    );

    /**
     * Process e-mail messages
     */
    $p = $email->run(true);
    echo PHP_EOL, $p == 0 ? "No e-mail to process." : "$p e-mail message(s) has been processed.", PHP_EOL;

    /**
     * Print saved files
     */
    echo PHP_EOL, "Saved files:", PHP_EOL, PHP_EOL;
    $email->printFiles(!is_cli());

  } catch (Exception $e) {
    echo $e->getMessage();
  }

  /**
   * Send html if running through the browser
   */
  if (!is_cli()) {
    echo "</body></html>";
  }
