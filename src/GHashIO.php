<?php

namespace Account\MiningPool;

use \Monolog\Logger;
use \Account\Account;
use \Account\Miner;
use \Account\DisabledAccount;
use \Account\SimpleAccountType;
use \Account\AccountFetchException;
use \Apis\FetchHttpException;
use \Apis\FetchException;
use \Apis\Fetch;
use \Openclerk\Currencies\CurrencyFactory;
use \Openclerk\Currencies\HashableCurrency;

/**
 * Represents the GHash.io mining pool.
 * Just returns hashrates, does not return balances (this is provided with CEX.io)
 */
class GHashIO extends AbstractMiner {

  public function getName() {
    return "GHash.io";
  }

  public function getCode() {
    return "ghashio";
  }

  public function getURL() {
    return "https://ghash.io/";
  }

  public function getFields() {
    return array(
      'api_username' => array(
        'title' => "Username",
        'regexp' => '#.+#',
      ),
      'api_key' => array(
        'title' => "API Key",
        'regexp' => '#^[A-Za-z0-9]{20,32}$#',
      ),
      'api_secret' => array(
        'title' => "API Secret",
        'regexp' => "#^[A-Za-z0-9]{20,32}$#",
      ),
    );
  }

  public function fetchSupportedCurrencies(CurrencyFactory $factory, Logger $logger) {
    // there is no public API to list supported currencies
    return array('btc', 'nmc', 'ixc', 'dvc');
  }

  public function fetchSupportedHashrateCurrencies(CurrencyFactory $factory, Logger $logger) {
    return $this->fetchSupportedCurrencies($factory, $logger);
  }

  /**
   * @return all account balances
   * @throws AccountFetchException if something bad happened
   */
  public function fetchBalances($account, CurrencyFactory $factory, Logger $logger) {

    $url = "https://cex.io/api/ghash.io/hashrate";
    $logger->info($url);

    try {
      $this->throttle($logger);
      $post_data = $this->generatePostData($account);
      $logger->info($post_data);
      $raw = Fetch::post($url, $post_data);
    } catch (FetchHttpException $e) {
      throw new AccountFetchException($e->getContent(), $e);
    }

    $json = Fetch::jsonDecode($raw);
    if (isset($json['error'])) {
      throw new AccountFetchException($json['error']);
    }

    $result = array();
    foreach ($this->fetchSupportedCurrencies($factory, $logger) as $cur) {
      $result[$cur] = array(
        'hashrate' => $json['last5m'] * 1e6 /* MH/s -> H/s */,
      );
    }
    return $result;

  }

  public function generatePostData($account) {
    $nonce = time();
    $message = $nonce . $account['api_username'] . $account['api_key'];
    $signature = strtoupper(hash_hmac("sha256", $message, $account['api_secret']));

    // generate the POST data string
    $req = array(
      'key' => $account['api_key'],
      'signature' => $signature,
      'nonce' => $nonce,
    );
    $post_data = http_build_query($req, '', '&');
    return $post_data;
  }

}
