<?php

namespace Amu\CliGrouperBundle\Entity;


use Symfony\Component\Validator\Constraints as Assert;


class User {

  protected $uid;
  protected $sn;
  protected $displayname;
  protected $mail;
  protected $tel;
  protected $memberof;

  /**
  * Set uid
  *
  * @param string $uid
 */
 public function setUid($uid)
 {
    $this->uid = $uid;
 }
 
 /**
  * Set sn
  *
  * @param string $sn
 */
 public function setSn($sn)
 {
    $this->sn = $sn;
 }
 
 /**
  * Set displayName
  *
  * @param string $name
 */
 public function setDisplayname($name)
 {
    $this->displayname = $name;
 }
 /**
  * Set mail
  *
  * @param string $mail
 */
 public function setMail($mail)
 {
    $this->mail = $mail;
 } 

 /**
  * Set telephone number
  *
  * @param string $tel
 */
 public function setTel($tel)
 {
    $this->tel = $tel;
 } 
 
 /**
  * Set memberof
  *
  * @param string $memberof
 */
 public function setMemberof($memberof)
 {
    $this->memberof = $memberof;
 } 
 
 /**
  * Get uid
  *
 */
 public function getUid()
 {
    return($this->uid);
 }
 
 /**
  * Get sn
  *
 */
 public function getSn()
 {
    return($this->sn);
 }
 
 /**
  * Get description
  *
 */
 public function getDisplayname()
 {
    return ($this->displayname);
 }
 
 /**
  * Get mail
  *
 */
 public function getMail()
 {
    return ($this->mail);
 } 
 
 /**
  * Get telephone number
  *
 */
 public function getTel()
 {
    return ($this->tel);
 } 

 /**
  * Get memberof
  *
 */
 public function getMemberof()
 {
    return ($this->memberof);
 } 
  
}