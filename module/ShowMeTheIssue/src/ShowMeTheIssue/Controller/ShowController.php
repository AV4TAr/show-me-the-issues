<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ShowMeTheIssue for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace ShowMeTheIssue\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Request;
use Bitbucket\API\Http\Listener\OAuthListener;
use Bitbucket\API\Repositories\Issues;
use HipChat\HipChat;

class ShowController extends AbstractActionController
{

    public function processAction()
    {
        $request = $this->getRequest();
        
        if (! $request instanceof Request) {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        $disableHipchat = $request->getParam('disable-hipchat');
        $addImage = $request->getParam('add-image', false);
        $defaultRoom = $request->getParam('hipchat-room');

        $config = $this->getServiceLocator()->get('config')['show-me-the-issue'];
        
        $oauthListener = new OAuthListener($config['bitbucket']['oauth']);
        
        
        $issue = new Issues();
        $issue->getClient()->addListener($oauthListener);
        
        foreach ($config['repo-mapping'] as $data) {
            try {
                if (isset($data['skip']) && $data['skip'] == true) {
                    continue;
                }
                $oauthListener = new OAuthListener($config['bitbucket']['oauth']);
                
                $issue = new Issues();
                $issue->getClient()->addListener($oauthListener);
                
                $issue_response = json_decode($issue->all($config['bitbucket']['account-name'], $data['repo'], $config['bitbucket']['issue-filters'])->getContent());
                
                $issue_msg = '<b>Daily issue report from code repository</b><br/>';
                $issue_msg .= '<a href="' . $data['issue-tracker-link'] . '">Issue tracker</a><br/>';
                if (count($issue_response->issues) == 0) {
                    $issue_msg .= '<b> NO ISSUES!!! Keep it up.</b><br/><img src="' . $config['no-issue-images'][rand(0, count($config['no-issue-images']) - 1)] . '"/>';
                } else {
                    foreach ($issue_response->issues as $issue) {
                        $issue_msg .= $issue->title . '<br/>';
                    }
                    if($addImage){
                        $issue_msg .= '<img src="' . $config['yes-issue-images'][rand(0, count($config['yes-issue-images']) - 1)] . '"/>';
                    }
                }
                $hc = new HipChat($config['hipchat']['api-token']);
                
                // Sends data to hipchat
                $hipchatRoom = $data['hipchat-room'];
                if ($defaultRoom) {
                    $hipchatRoom = $defaultRoom;
                }
                
                if (!$disableHipchat) {
                    $hc->message_room($hipchatRoom, 'Issues', $issue_msg);
                }
    
                //echo nl2br($issue_msg);
                //echo "<br>-------------------------------------------------\r";
            } catch (\Exception $e){
                echo $e->getMessage();
            }
        }
        
        return '--- Finished ---';
    }
}
