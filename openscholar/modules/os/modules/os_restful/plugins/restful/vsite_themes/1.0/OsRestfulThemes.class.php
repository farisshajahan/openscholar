<?php
$git_path = libraries_get_path('git');
require_once(DRUPAL_ROOT . '/' . $git_path . '/autoload.php');
use GitWrapper\GitWrapper;
use GitWrapper\GitException;

/**
 * @file
 * Contains \RestfulDataProviderDbQuery
 */
class OsRestfulThemes extends \RestfulBase implements \RestfulDataProviderInterface {
  /**
   * Overrides \RestfulBase::controllersInfo().
   */
  public static function controllersInfo() {
    return array(
      '' => array(
        // If they don't pass a menu-id then display nothing.
        \RestfulInterface::POST => 'fetchBranches',
      ),
      // We don't know what the ID looks like, assume that everything is the ID.
      '^.*$' => array(
        \RestfulInterface::POST => 'uploadZipTheme',
      ),
    );
  }


  /**
   * {@inheritdoc}
  */
  public function publicFieldsInfo() {}

  public function uploadZipTheme() {}

  public function fetchBranches() {

    $branches = array();
    // Initiate the return message
    $subtheme->msg = array();
    $branch_name = isset($this->request['git']) ? urldecode($this->request['git']) : '';

    if ($repository_address = !empty($branch_name) ? trim($branch_name) : FALSE) {
      $wrapper = new GitWrapper();
      $wrapper->setPrivateKey('.');

      $path = variable_get('file_public_path', conf_path() . '/files') . '/subtheme/' . $repository_address;

      // @todo: Remove the github hardcoding.
      $path = str_replace(array('http://', 'https://', '.git', 'git@github.com:'), '', $path);

      if (!file_exists($path)) {
        drupal_mkdir($path, NULL, TRUE);
      }

      $git = $wrapper->workingCopy($path);

      if (!$git->isCloned()) {
        try {
          $git->clone($repository_address);
          $git->setCloned(TRUE);
        }
        catch (GitException $e) {
          // Can't clone the repo.
          $subtheme->msg[] = t('Could not clone @repository, error @error', array('@repository' => $repository_address, '@error' => $e->getMessage(), 'warning'));
        }
      }

      if ($git->isCloned()) {
        try {
          foreach ($git->getBranches()->remote() as $branch) {
            if (strpos($branch, ' -> ') !== FALSE) {
              // A branch named "origin/HEAD  -> xyz" is provided by the class, we
              // don't need it.
              continue;
            }
            $branches[str_replace(' ', '_', $branch)] = $branch;
          }
        }
        catch (GitException $e) {
        }
      }

      $sub_theme = new SubTheme();
      $sub_theme->path = $path;
    }

    $valid_repo = FALSE;

    if ($branches) {
      $valid_repo = TRUE;
    }
    elseif (!$branches && $repository_address) {
      $subtheme->msg[] = t('Git repository is wrong.'); 
    }
    if ($valid_repo) {
      // return msg with $branches;
      $subtheme->branches = $branches;
    }

    return array(
      'branches' => $subtheme->branches,
      'msg' => $subtheme->msg,
      'path' => $sub_theme->path,
    );
  }

}