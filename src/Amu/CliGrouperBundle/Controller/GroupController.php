<?php

namespace Amu\CliGrouperBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Amu\CliGrouperBundle\Entity\Group;
use Amu\CliGrouperBundle\Form\GroupCreateType;
use Amu\CliGrouperBundle\Form\GroupModifType;
use Amu\CliGrouperBundle\Form\GroupSearchType;
use Amu\CliGrouperBundle\Entity\User;
use Amu\CliGrouperBundle\Entity\Member;
use Amu\CliGrouperBundle\Form\MemberType;
use Amu\CliGrouperBundle\Form\GroupEditType;
use Amu\CliGrouperBundle\Form\UserEditType;
use Amu\CliGrouperBundle\Entity\Membership;
use Amu\CliGrouperBundle\Form\PrivateGroupCreateType;
use Amu\CliGrouperBundle\Form\PrivateGroupEditType;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * group controller
 * @Route("/group")
 * 
 */
class GroupController extends Controller {
    
    /**
     * Fonction de récupération du service LDAP
     * @return  \Amu\AppBundle\Service\Ldap
    */
    private function getLdap() {
        return $this->get('CliGrouper.ldap');
    }

    /**
     * Affiche tous les groupes
     *
     * @Route("/tous_les_groupes",name="tous_les_groupes")
     * @Template()
    */
    public function touslesgroupesAction() {        
        // Variables pour l'affichage "dossier" avec javascript 
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;
          
        // Récupération des groupes dans le LDAP
        $arData=$this->getLdap()->arDatasFilter("(objectClass=groupofNames)",array("cn","description","amuGroupFilter"));
         
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]["dn"], "ou=private")) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]["cn"][0]);
                $groups[$i]->setDescription($arData[$i]["description"][0]);
                $groups[$i]->setAmugroupfilter($arData[$i]["amugroupfilter"][0]);
                $groups[$i]->setAmugroupadmin("");

                // Mise en forme pour la présentation "dossier" avec javascript
                $arEtages = preg_split('/[:]+/', $arData[$i]["cn"][0]);
                $NbEtages = count($arEtages);
                $groups[$i]->setEtages($arEtages);
                $groups[$i]->setNbetages($NbEtages);
                $groups[$i]->setLastnbetages($NbEtagesPrec);

                // on marque la différence entre les dossiers d'affichage des groupes N et N-1
                $lastopen = 0;
                for ($j=0;$j<$NbEtagesPrec;$j++) {
                    if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                        $lastopen = $j ;
                        $groups[$i]->setLastopen($lastopen);
                        break;
                    }
                }

                if (($NbEtagesPrec>=1) && ($lastopen == 0))
                    $groups[$i]->setLastopen($NbEtagesPrec-1);

                // on garde le nom du groupe précédent dans la liste
                $arEtagesPrec = $groups[$i]->getEtages();
                $NbEtagesPrec = $groups[$i]->getNbetages();
            }   
        }
        
        return array('groups' => $groups);
    }

    /**
     * Affiche tous les groupes privés
     *
     * @Route("/tous_les_groupes_prives",name="tous_les_groupes_prives")
     * @Template()
     */
    public function touslesgroupesprivesAction() {
        // Récupération tous les groupes privés du LDAP  
        $arData=$this->getLdap()->arDatasFilter("(objectClass=groupofNames)",array("cn","description","amuGroupFilter"));
         
        // Initialisation tableau des entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            // on ne garde que les groupes prives
            if (strstr($arData[$i]["dn"], "ou=private")) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]["cn"][0]);
                $groups[$i]->setDescription($arData[$i]["description"][0]);
            }
        }

        return array('groups' => $groups);
    }
 
    /**
     * Affiche tous les groupes dont l'utilisateur est administrateur
     *
     * @Route("/mes_groupes",name="mes_groupes")
     * @Template()
     */
    public function mesgroupesAction(Request $request) {
        // Variables pour l'affichage "dossier" avec javascript 
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;
        
        // Récupération des groupes dont l'utilisateur courant est administrateur (on ne récupère que les groupes publics)
        $arData=$this->getLdap()->arDatasFilter("amuGroupAdmin=uid=".$request->getSession()->get('login').",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));

        // Initialisation tableau des entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]["cn"][0]);
            $groups[$i]->setDescription($arData[$i]["description"][0]);
            $groups[$i]->setAmugroupfilter($arData[$i]["amugroupfilter"][0]);
            
            // Mise en forme pour la présentation "dossier" avec javascript
            $arEtages = preg_split('/[:]+/', $arData[$i]["cn"][0]);
            $NbEtages = count($arEtages);
            $groups[$i]->setEtages($arEtages);
            $groups[$i]->setNbetages($NbEtages);
            $groups[$i]->setLastnbetages($NbEtagesPrec);
                        
            // on marque la différence entre les dossiers d'affichage des groupes N et N-1
            $lastopen = 0;
            for ($j=0;$j<$NbEtagesPrec;$j++) {
                if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                    $lastopen = $j ;
                    $groups[$i]->setLastopen($lastopen);
                    break;
                }
            }
            
            if (($NbEtagesPrec>=1) && ($lastopen == 0))
                $groups[$i]->setLastopen($NbEtagesPrec-1);
            
            // on garde le nom du groupe précédent dans la liste
            $arEtagesPrec = $groups[$i]->getEtages();
            $NbEtagesPrec = $groups[$i]->getNbetages();
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Affiche tous les groupes dont l'utilisateur est membre
     *
     * @Route("/mes_appartenances",name="mes_appartenances")
     * @Template()
     */
    public function mesappartenancesAction(Request $request) {
        // Variables pour l'affichage "dossier" avec javascript 
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;
        
        // Récupération des groupes dont l'utilisateur courant est membre
        $arData=$this->getLdap()->arDatasFilter("member=uid=".$request->getSession()->get('login').",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));
        
        // Initialisation du tableau d'entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]["dn"], "ou=private")) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]["cn"][0]);
                $groups[$i]->setDescription($arData[$i]["description"][0]);
                $groups[$i]->setAmugroupfilter($arData[$i]["amugroupfilter"][0]);

                // Mise en forme pour la présentation "dossier" avec javascript
                $arEtages = preg_split('/[:]+/', $arData[$i]["cn"][0]);
                $NbEtages = count($arEtages);
                $groups[$i]->setEtages($arEtages);
                $groups[$i]->setNbetages($NbEtages);
                $groups[$i]->setLastnbetages($NbEtagesPrec);

                // on marque la différence entre les dossiers d'affichage des groupes N et N-1
                $lastopen = 0;
                for ($j=0;$j<$NbEtagesPrec;$j++) {
                    if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                        $lastopen = $j ;
                        $groups[$i]->setLastopen($lastopen);
                        break;
                    }
                }

                if (($NbEtagesPrec>=1) && ($lastopen == 0))
                    $groups[$i]->setLastopen($NbEtagesPrec-1);

                // on garde le nom du groupe précédent dans la liste
                $arEtagesPrec = $groups[$i]->getEtages();
                $NbEtagesPrec = $groups[$i]->getNbetages();
            }
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Affiche tous les groupes privés dont l'utilisateur est membre
     *
     * @Route("/mes_appartenances_privees",name="mes_appartenances_privees")
     * @Template()
     */
    public function mesappartenancespriveesAction(Request $request) {
        // Récupération des groupes privés dont l'utilisateur courant est membre
        $arData=$this->getLdap()->arDatasFilter("member=uid=".$request->getSession()->get('login').",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));
        
        // Initialisation du tableau d'entités Group
        $groups = new ArrayCollection();
        $nb_groups=0;
        for ($i=0; $i<$arData["count"]; $i++) {
            // on ne garde que les groupes privés
            if (strstr($arData[$i]["dn"], "ou=private")) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]["cn"][0]);
                $groups[$i]->setDescription($arData[$i]["description"][0]);
                $groups[$i]->setAmugroupfilter($arData[$i]["amugroupfilter"][0]);
                $nb_groups++;
            }
        }
        
        return array('groups' => $groups, 'nb_groups' => $nb_groups);
    }
        
    /**
     * Recherche de groupes
     *
     * @Route("/search/{opt}/{cn}/{uid}",name="group_search")
     * @Template()
     */
    public function searchAction(Request $request, $opt='search', $uid='', $cn=0) {
        // Déclaration variables
        $groupsearch = new Group();
        $groups = array();
        
        // Création du formulaire de recherche de groupe
        $form = $this->createForm(new GroupSearchType(), new Group(), array('attr' => array('novalidate' => 'novalidate')));
        $form->handleRequest($request);
        if ($form->isValid()) {
            // Récupération des données du formulaire
            $groupsearch = $form->getData(); 
            // Suivant l'option d'où on vient
            if (($opt=='search')||($opt=='mod')||($opt=='del')){
                // si on a sélectionné un proposition de la liste d'autocomplétion
                if ($cn==true) {
                    // On teste si on est sur le message "... Résultat partiel ..."
                    if ($groupsearch->getCn() == "... Résultat partiel ...") {
                        $this->get('session')->getFlashBag()->add('flash-notice', 'Le nom du groupe est invalide');
                        return $this->redirect($this->generateUrl('group_search', array('opt'=>$opt, 'uid'=>$uid, 'cn'=>$cn)));
                    }
                    // Recherche exacte des groupes dans le LDAP
                    $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=" . $groupsearch->getCn() . "))",array("cn","description","amuGroupFilter"));
                }
                else {
                    // Recherche avec * des groupes dans le LDAP directement 
                    $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=*" . $groupsearch->getCn() . "*))",array("cn","description","amuGroupFilter"));
                }
                
                // si c'est un gestionnaire, on ne renvoie que les groupes dont il est admin
                $tab_cn_admin = array();
                if (true === $this->get('security.context')->isGranted('ROLE_GESTIONNAIRE')) {
                    // Recup des groupes dont l'utilisateur est admin
                    $arDataAdmin = $this->getLdap()->arDatasFilter("amuGroupAdmin=uid=".$request->getSession()->get('login').",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));
                    for($i=0;$i<$arDataAdmin["count"];$i++)
                        $tab_cn_admin[$i] = $arDataAdmin[$i]["cn"][0];
                }
               
                $nb = 0;
                for ($i=0; $i<$arData["count"]; $i++) {
                    // on ne garde que les groupes publics
                    if (!strstr($arData[$i]["dn"], "ou=private")) {
                        $groups[$i] = new Group();
                        $groups[$i]->setCn($arData[$i]["cn"][0]);
                        $groups[$i]->setDescription($arData[$i]["description"][0]);
                        $groups[$i]->setAmugroupfilter($arData[$i]["amugroupfilter"][0]);
                        $groups[$i]->setDroits('Aucun');

                        // Droits DOSI seulement en visu
                        if (true === $this->get('security.context')->isGranted('ROLE_DOSI')) {
                            $groups[$i]->setDroits('Voir');
                        }

                        // Droits gestionnaire seulement sur les groupes dont il est admin
                        if (true === $this->get('security.context')->isGranted('ROLE_GESTIONNAIRE')) {
                            foreach ($tab_cn_admin as $cn_admin) {    
                                if ($cn_admin==$arData[$i]["cn"][0]) {
                                    $groups[$i]->setDroits('Modifier');
                                    break;
                                }
                            }
                        }

                        // Droits Admin
                        if (true === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
                            $groups[$i]->setDroits('Modifier');
                        }
                        $nb++;
                    }
                }
            
                // Mise en session des résultats de la recherche
                $this->container->get('request')->getSession()->set('groups', $groups);

                // Si on a un seul résultat de recherche, affichage direct du groupe concerné en fonction des droits
                if ($opt=='search') {
                    if ($nb==1) {
                        if ($groups[0]->getDroits()=='Modifier') {
                            return $this->redirect($this->generateUrl('group_update', array('cn'=>$groups[0]->getCn(), 'liste' => 'recherchegroupe')));
                        }

                        if ($groups[0]->getDroits()=='Voir') {
                           return $this->redirect($this->generateUrl('voir_groupe', array('cn'=>$groups[0]->getCn(), 'mail' => true, 'liste' => 'recherchegroupe')));
                        }
                    }
                }
  
                return $this->render('AmuCliGrouperBundle:Group:recherchegroupe.html.twig',array('groups' => $groups, 'opt' => $opt, 'uid' => $uid));
            }
            else {
                if ($opt=='add') {
                    // Renvoi vers le fonction group_add
                    return $this->redirect($this->generateUrl('group_add', array('cn_search'=>$groupsearch->getCn(), 'uid'=>$uid, 'flag_cn'=> $cn)));
                }
            }
        }
        
        return $this->render('AmuCliGrouperBundle:Group:groupesearch.html.twig', array('form' => $form->createView(), 'opt' => $opt, 'uid' => $uid));
        
    }
    
    /**
     * Recherche de groupes pour la suppression
     *
     * @Route("/searchdel",name="group_search_del")
     * @Template()
     */
    public function searchdelAction(Request $request) {
        return $this->redirect($this->generateUrl('group_search', array('opt' => 'del', 'uid'=>'')));
    }
    
    /**
     * Recherche de groupes pour la modification
     *
     * @Route("/searchmod",name="group_search_modify")
     * @Template()
     */
    public function searchmodAction(Request $request) {
        return $this->redirect($this->generateUrl('group_search', array('opt' => 'mod', 'uid'=>'')));
    }
    
    /**
     * Ajout de personnes dans un groupe
     *
     * @Route("/add/{cn_search}/{uid}/{flag_cn}",name="group_add")
     * @Template("AmuCliGrouperBundle:Group:recherchegroupeadd.html.twig")
     */
    public function addAction(Request $request, $cn_search='', $uid='', $flag_cn=0) {
        // Dans le cas d'un gestionnaire
        if (true === $this->get('security.context')->isGranted('ROLE_GESTIONNAIRE')) {
            // Recup des groupes dont l'utilisateur courant (logué) est admin
            $arDataAdminLogin = $this->getLdap()->arDatasFilter("amuGroupAdmin=uid=".$request->getSession()->get('login').",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));
            for($i=0;$i<$arDataAdminLogin["count"];$i++) 
                $tab_cn_admin_login[$i] = $arDataAdminLogin[$i]["cn"][0];
        }
        
        // Récupération utilisateur et début d'initialisation de l'objet
        $user = new User();
        $user->setUid($uid);
        // Récup infos utilisateur dans le LDAP
        $arDataUser=$this->getLdap()->arDatasFilter("uid=".$uid, array('displayname', 'memberof'));
        $user->setDisplayname($arDataUser[0]['displayname'][0]);
        
        // Utilisateur initial pour détecter les modifications
        $userini = new User();
        $userini->setUid($uid);
        $userini->setDisplayname($arDataUser[0]['displayname'][0]);
        
        // Mise en forme du tableau contenant les cn des groupes dont l'utilisateur recherché est membre
        $tab = array_splice($arDataUser[0]['memberof'], 1);
        $tab_cn = array();
        foreach($tab as $dn) {
            $tab_cn[] = preg_replace("/(cn=)(([A-Za-z0-9:._-]{1,}))(,ou=groups.*)/", "$3", $dn);
        }
        // Récupération des groupes dont l'utilisateur recherché est admin
        $arDataAdmin=$this->getLdap()->arDatasFilter("amuGroupAdmin=uid=".$uid.",ou=people,dc=univ-amu,dc=fr",array("cn", "description", "amugroupfilter"));
        $tab_cn_admin = array();
        for($i=0;$i<$arDataAdmin["count"];$i++) {
            $tab_cn_admin[$i] = $arDataAdmin[$i]["cn"][0];
        }
        
        // Si on a sélectionné une proposition dans la liste d'autocomplétion
        if ($flag_cn==true) {
            // On teste si on est sur le message "... Résultat partiel ..."
            if ($cn_search == "... Résultat partiel ...") {
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le nom du groupe est invalide');                        
                return $this->redirect($this->generateUrl('group_search', array('opt'=>'add', 'uid'=>$uid, 'cn'=>$cn)));
            }
            
            // Recherche exacte sur le cn sélectionné dans le LDAP
            $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=" . $cn_search . "))",array("cn","description","amuGroupFilter"));
        }
        else {
            // Recherche avec * dans le LDAP
            $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=*" . $cn_search . "*))",array("cn","description","amuGroupFilter"));             
        }
        
        // Récupération des groupes publics issus de la recherche
        $cpt=0;
        for ($i=0; $i<$arData["count"]; $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]["dn"], "ou=private")) {
                $tab_cn_search[$cpt] = $arData[$i]["cn"][0];
                $cpt++;
            }
        }
                           
        // on remplit l'objet user avec les groupes retournés par la recherche LDAP
        $memberships = new ArrayCollection();
        // Idem pour l'objet userini
        $membershipsini = new ArrayCollection();
        foreach($tab_cn_search as $groupname)
        {
            $membership = new Membership();
            $membership->setGroupname($groupname);
            $membership->setDroits('Aucun');
            $membershipini = new Membership();
            $membershipini->setGroupname($groupname);
            $membershipini->setDroits('Aucun');
            // Remplissage des droits "membre"
            foreach($tab_cn as $cn) {
                if ($cn==$groupname) {
                    $membership->setMemberof(TRUE);
                    $membershipini->setMemberof(TRUE);
                    break;
                }
                else {
                    $membership->setMemberof(FALSE);
                    $membershipini->setMemberof(FALSE);
                 }
            }
            
            //Remplissage des droits admin
            foreach($tab_cn_admin as $cn) {
                if ($cn==$groupname) {
                    $membership->setAdminof(TRUE);
                    $membershipini->setAdminof(TRUE);
                    break;
                }
                else {
                    $membership->setAdminof(FALSE);
                    $membershipini->setAdminof(FALSE);
                 }
            }
                        
            // Gestion droits pour un gestionnaire
            if (true === $this->get('security.context')->isGranted('ROLE_GESTIONNAIRE')) {
                foreach($tab_cn_admin_login as $cn) {
                    if ($cn==$groupname) {
                        $membership->setDroits('Modifier');
                        $membershipini->setDroits('Modifier');
                        break;
                    }
                }
            }
            
            // Gestion droits pour un admin de l'appli
            if (true === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
                $membership->setDroits('Modifier');
                $membershipini->setDroits('Modifier');  
            }
            
            $memberships[] = $membership;
            $membershipsini[] = $membershipini;
        }
        $user->setMemberships($memberships);      
        $userini->setMemberships($membershipsini);
        
        // Formulaire
        $editForm = $this->createForm(new UserEditType(), $user, array(
            'action' => $this->generateUrl('group_add', array('cn_search'=> $cn_search, 'uid' => $uid, 'flag_cn' => $flag_cn)),
            'method' => 'POST',
        ));
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            // Initialisation des entités
            $userupdate = new User();
            $m_update = new ArrayCollection();      
            
            // Récupération des données du formulaire
            $userupdate = $editForm->getData();
            $m_update = $userupdate->getMemberships();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            $adm = $request->getSession()->get('login');
            
            // Pour chaque appartenance
            for ($i=0; $i<sizeof($m_update); $i++) {
                $memb = $m_update[$i];
                $dn_group = "cn=" . $memb->getGroupname() . ", ou=groups, dc=univ-amu, dc=fr";
                $gr = $memb->getGroupname();
                
                // Traitement des membres  
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMemberof() != $membershipsini[$i]->getMemberof()) {
                    if ($memb->getMemberof()) {
                        // Ajout utilisateur dans groupe
                        $r = $this->getLdap()->addMemberGroup($dn_group, array($uid));
                        // Log des modifications
                        if ($r==true) 
                            syslog(LOG_INFO, "add_member by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $gr, user : $uid");
                    }
                    else {
                        // Suppression utilisateur du groupe
                        $r = $this->getLdap()->delMemberGroup($dn_group, array($uid));
                        if ($r)
                            syslog(LOG_INFO, "del_member by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $gr, user : $uid");
                    }
                }
                // Traitement des admins
                // Si il y a changement pour admin, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getAdminof() != $membershipsini[$i]->getAdminof()) {
                    if ($memb->getAdminof()) {
                        // Ajout admin dans le groupe
                        $r = $this->getLdap()->addAdminGroup($dn_group, array($uid));
                        if ($r)
                            syslog(LOG_INFO, "add_admin by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $gr, user : $uid ");
                    }
                    else {
                        // Suppression admin du groupe
                        $r = $this->getLdap()->delAdminGroup($dn_group, array($uid)); 
                        if ($r)
                            syslog(LOG_INFO, "del_admin by $adm : group : $gr, user : $uid ");
                        else
                            syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $gr, user : $uid ");
                    }
                }
            }
            // Ferme fichier de log
            closelog();
            // Notification message flash
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');
            
            // Retour à la page update d'un utilisateur
            return $this->redirect($this->generateUrl('user_update', array('uid'=>$uid)));
        }
         
        // Affichage via le fichier twig
        return array(
            'user'      => $user,
            'cn_search' => $cn_search,
            'flag_cn' => $flag_cn,
            'form'   => $editForm->createView(),
        );
    }
    
    /**
     * Voir les membres et administrateurs d'un groupe.
     *
     * @Route("/voir/{cn}/{mail}/{liste}", name="voir_groupe")
     * @Template()
     */
    public function voirAction(Request $request, $cn, $mail, $liste)
    {
        // Initialisation des tableaux d'entités
        $users = array();
        $admins = array(); 
        
        // Récup du groupe 
        $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=" . $cn . "))",array("cn","description","amuGroupFilter"));
        $amugroupfilter = $arData[0]["amugroupfilter"][0];
        
        // Recherche des membres dans le LDAP
        $arUsers = $this->getLdap()->getMembersGroup($cn);
        // on remplit le tableau d'entités
        for ($i=0; $i<$arUsers["count"]; $i++) {                     
            $users[$i] = new User();
            $users[$i]->setUid($arUsers[$i]["uid"][0]);
            $users[$i]->setSn($arUsers[$i]["sn"][0]);
            $users[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            if ($mail=='true')
                $users[$i]->setMail($arUsers[$i]["mail"][0]);
            $users[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
        }
        
        // Recherche des administrateurs du groupe
        $arAdmins = $this->getLdap()->getAdminsGroup($cn);
        // on remplit le tableau d'entités        
        for ($i=0; $i<$arAdmins[0]["amugroupadmin"]["count"]; $i++) {  
            $uid = preg_replace("/(uid=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", $arAdmins[0]["amugroupadmin"][$i]);
            $result = $this->getLdap()->arUserInfos($uid, array("uid", "sn", "displayname", "mail", "telephonenumber"));
            $admins[$i] = new User();
            $admins[$i]->setUid($result["uid"]);
            $admins[$i]->setSn($result["sn"]);
            $admins[$i]->setDisplayname($result["displayname"]);
            $admins[$i]->setMail($result["mail"]);
            $admins[$i]->setTel($result["telephonenumber"]);
        }
        
        // Affichage via le fichier twig
        return array('cn' => $cn,
                    'amugroupfilter' => $amugroupfilter,
                    'nb_membres' => $arUsers["count"], 
                    'users' => $users,
                    'nb_admins' => $arAdmins[0]["amugroupadmin"]["count"],
                    'admins' => $admins,
                    'liste' => $liste);
    }
    
     /**
     * Voir les membres et administrateurs d'un groupe privé.
     *
     * @Route("/voir_prive/{cn}/{opt}", name="voir_groupe_prive")
     * @Template()
     */
    public function voirpriveAction(Request $request, $cn, $opt)
    {
        $users = array();
        // Récupération du propriétaire du groupe
        $uid_prop = strstr($cn, ":", TRUE);
        $result = $this->getLdap()->arUserInfos($uid_prop, array("uid", "sn", "displayname", "mail", "telephonenumber"));
        $proprietaire = new User();
        $proprietaire->setUid($result["uid"]);
        $proprietaire->setSn($result["sn"]);
        $proprietaire->setDisplayname($result["displayname"]);
        $proprietaire->setMail($result["mail"]);
        $proprietaire->setTel($result["telephonenumber"]);
        
        // Recherche des membres dans le LDAP
        $arUsers = $this->getLdap()->getMembersGroup($cn.",ou=private");
                 
        for ($i=0; $i<$arUsers["count"]; $i++) {                     
            $users[$i] = new User();
            $users[$i]->setUid($arUsers[$i]["uid"][0]);
            $users[$i]->setSn($arUsers[$i]["sn"][0]);
            $users[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            if ($mail=='true')
                $users[$i]->setMail($arUsers[$i]["mail"][0]);
            $users[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
        }
        
        // Affichage via twig
        return array('cn' => $cn,
                    'nb_membres' => $arUsers["count"], 
                    'proprietaire' => $proprietaire,
                    'users' => $users,
                    'opt' => $opt);
    }
        
    /**
     * Création d'un groupe
     *
     * @Route("/create",name="group_create")
     * @Template("AmuCliGrouperBundle:Group:groupe.html.twig")
     */
    public function createAction(Request $request) {
        // Initialisation des entités
        $group = new Group();
        $groups = array();
        
        // Création du formulaire de création de groupe
        $form = $this->createForm(new GroupCreateType(), new Group());
        $form->handleRequest($request);
        if ($form->isValid()) {
            // Récupération des données
            $group = $form->getData();
            
            // Log création de groupe
            openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            $adm = $request->getSession()->get('login');
                
            // Création du groupe dans le LDAP
            $infogroup = $group->infosGroupeLdap();
            $b = $this->getLdap()->createGroupeLdap($infogroup['dn'], $infogroup['infos']);
            if ($b==true) {          
                // affichage groupe créé
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été créé');
                $groups[0] = $group;
                $cn = $group->getCn();
                
                // Log création OK
                syslog(LOG_INFO, "create_group by $adm : group : $cn");
               
                // Affichage via fichier twig
                return $this->render('AmuCliGrouperBundle:Group:creationgroupe.html.twig',array('groups' => $groups));
            }
            else {
                // affichage erreur
                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la création du groupe');
                $groups[0] = $group;
                $cn = $group->getCn();
                
                // Log erreur
                syslog(LOG_ERR, "LDAP ERREUR : create_group by $adm : group : $cn");
                
                // Retour à la page contenant le formulaire de création de groupe
                return $this->render('AmuCliGrouperBundle:Group:groupe.html.twig', array('form' => $form->createView()));
            }
            
            // Ferme le fichier de log
            closelog();
        }
        
        // Affichage formulaire de création de groupe
        return $this->render('AmuCliGrouperBundle:Group:groupe.html.twig', array('form' => $form->createView()));
    }
    
    /**
     * Création d'un groupe privé
     *
     * @Route("/private/create/{nb_groups}",name="private_group_create")
     * @Template("AmuCliGrouperBundle:Group:createprivate.html.twig")
     */
    public function createPrivateAction(Request $request, $nb_groups) {
        // Limite sur le nombre de groupes privés qu'il est possible de créer
        if ($nb_groups>20){
            return $this->render('AmuCliGrouperBundle:Group:limite.html.twig');
        }
        
        // Initialisation des entités
        $group = new Group();
        $groups = array();
                
        // Création du formulaire
        $form = $this->createForm(new PrivateGroupCreateType(), new Group());
        $form->handleRequest($request);
        if ($form->isValid()) {
            // Récupération de l'entrée utilisateur
            $group = $form->getData();
            
            // Vérification de la validité du champ cn : pas d'espaces, accents, caractères spéciaux
            $test = preg_match("#^[A-Za-z0-9-_]+$#i", $group->getCn());
            if ($test>0) {
                // le nom du groupe est valide, on peut le créer
                // Log création de groupe
                openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
                $adm = $request->getSession()->get('login');
                
                // Création du groupe dans le LDAP
                $infogroup = $group->infosGroupePriveLdap($adm);
                $b = $this->getLdap()->createGroupeLdap($infogroup['dn'], $infogroup['infos']);
                if ($b==true) { 
                    //Le groupe a bien été créé
                    $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été créé');
                    $groups[0] = $group;
                    $cn = $adm.":".$group->getCn();
                    $group->setCn($cn);

                    // Log création OK
                    syslog(LOG_INFO, "create_private_group by $adm : group : $cn");

                    // Affichage création OK
                    return $this->render('AmuCliGrouperBundle:Group:creationgroupeprive.html.twig',array('groups' => $groups));
                }
                else {
                    // affichage erreur
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la création du groupe');
                    $groups[0] = $group;
                    $cn = $group->getCn();

                    // Log erreur
                    syslog(LOG_ERR, "LDAP ERREUR : create_private_group by $adm : group : $cn");

                    // Affichage page 
                    return $this->render('AmuCliGrouperBundle:Group:createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
                }

                // Ferme le fichier de log
                closelog();
            }
            else {
                // le nom du groupe n'est pas valide, notification à l'utilisateur
                // affichage erreur
                $this->get('session')->getFlashBag()->add('flash-error', 'Le nom du groupe est invalide. Merci de supprimer les accents et caractères spéciaux.');
                    
                // Affichage page du formulaire
                return $this->render('AmuCliGrouperBundle:Group:createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
            }
        }
        return $this->render('AmuCliGrouperBundle:Group:createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
    }
    
     /**
     * Supprimer un groupe.
     *
     * @Route("/delete/{cn}", name="group_delete")
     * @Template()
     */
    public function deleteAction(Request $request, $cn)
    {
        // Log suppression de groupe
        openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
        $adm = $request->getSession()->get('login');
        
        //Suppression du groupe dans le LDAP
        $b = $this->getLdap()->deleteGroupeLdap($cn);
        if ($b==true) {
            //Le groupe a bien été supprimé
            $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été supprimé');
            
            // Log
            syslog(LOG_INFO, "delete_group by $adm : group : $cn");
            
            return $this->render('AmuCliGrouperBundle:Group:suppressiongroupe.html.twig',array('cn' => $cn));
        }
        else {
            // Log erreur
            syslog(LOG_ERR, "LDAP ERROR : delete_group by $adm : group : $cn");
            // affichage erreur
            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la suppression du groupe');
            // Retour page
            return $this->render('AmuCliGrouperBundle:Group:groupesearch.html.twig', array('form' => $form->createView()));
        }
        
        // Ferme fichier de log
        closelog();
    }
    
    /**
     * Choisir un groupe privé à supprimer
     *
     * @Route("/private/delete",name="private_group_delete")
     * @Template("AmuCliGrouperBundle:Group:deleteprivate.html.twig")
     */
    public function deletePrivateAction() {
        
        $uid = $this->container->get('request')->getSession()->get('login');
        // Recherche des groupes dans le LDAP
        $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=".$uid.":*))",array("cn","description"));
    
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]["cn"][0]);
            $groups[$i]->setDescription($arData[$i]["description"][0]);
            
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Supprimer un groupe privé.
     *
     * @Route("/private/del_1/{cn}", name="private_group_del_1")
     * @Template()
     */
    public function del1PrivateAction(Request $request, $cn) {
        // Log suppression de groupe
        openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
        $adm = $request->getSession()->get('login');
        
        // Suppression du groupe dans le LDAP
        $b = $this->getLdap()->deleteGroupeLdap($cn.",ou=private");
        if ($b==true) {
            //Le groupe a bien été supprimé
            $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été supprimé');
            
            // Log
            syslog(LOG_INFO, "delete_private_group by $adm : group : $cn");                        
        }
        else {
            // Log erreur
            syslog(LOG_ERR, "LDAP ERROR : delete_private_group by $adm : group : $cn");
            // affichage erreur
            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la suppression du groupe');
        }
        
        // Ferme fichier de log
        closelog();
        
        // Recup des infos pour affichage des groupes restants
        $uid = $this->container->get('request')->getSession()->get('login');
        // Recherche des groupes dans le LDAP
        $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=".$uid.":*))",array("cn","description"));
    
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]["cn"][0]);
            $groups[$i]->setDescription($arData[$i]["description"][0]);
            
        }
        
        return $this->render('AmuCliGrouperBundle:Group:deleteprivate.html.twig', array('groups' => $groups));
    }
    
    /**
     * Modifier un groupe.
     *
     * @Route("/modify/{cn}/{desc}/{filt}", name="group_modify")
     * @Template()
     */
    public function modifyAction(Request $request, $cn, $desc, $filt)
    {
        $group = new Group();
        $groups = array();
        
        $dn = "cn=".$cn.", ou=groups, dc=univ-amu, dc=fr";
        
        // Pré-remplir le formulaire avec les valeurs actuelles du groupe
        $group->setCn($cn);
        $group->setDescription($desc);
        if ($filt=="no")
            $group->setAmugroupfilter("");
        else
            $group->setAmugroupfilter($filt);
        
        $form = $this->createForm(new GroupModifType(), $group);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $groupmod = new Group();
            $groupmod = $form->getData();
            
            // Log modif de groupe
            openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            $adm = $request->getSession()->get('login');
            
            // Cas particulier de la suppression amugroupfilter
            if (($filt != "no") && ($groupmod->getAmugroupfilter() == "")) {
                // Suppression de l'attribut
                $b = $this->getLdap()->delAmuGroupFilter($dn, $filt);
                // Log Erreur LDAP
                syslog(LOG_ERR, "LDAP ERROR : modif_group by $adm : group : $cn, delAmuGroupFilter");
                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la modification du groupe');
                return $this->render('AmuCliGrouperBundle:Group:groupem.html.twig', array('form' => $form->createView(), 'group' => $group));
            }
                
            // Modification du groupe dans le LDAP
            $infogroup = $groupmod->infosGroupeLdap();
            //echo "<b> DEBUT DEBUG INFOS <br>" . "<br><B>Infos groupe</B>=><FONT color =green><PRE>" . print_r($infogroup, true) . "</PRE></FONT></FONT>";
            $b = $this->getLdap()->modifyGroupeLdap($dn, $infogroup['infos']);
            // echo "<b> DEBUT DEBUG INFOS <br>" . "<br><B>filt</B>=><FONT color =green><PRE>" . print_r($groupmod) . "</PRE></FONT></FONT>";
            if ($b==true)
            {
                //Le groupe a bien été modifié
                //echo "<b> DEBUT DEBUG INFOS <br>" . "<br><B>retour create groupe ldap</B>=><FONT color =green><PRE>" . $b . "</PRE></FONT></FONT>";
                // Log modif de groupe OK
                syslog(LOG_INFO, "modif_group by $adm : group : $cn");
                
                 // affichage groupe créé
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été modifié');
                $groups[0] = $group;
                return $this->render('AmuCliGrouperBundle:Group:modifgroupe.html.twig',array('groups' => $groups));
            }
            else 
            {
                // Log Erreur LDAP
                syslog(LOG_ERR, "LDAP ERROR : modif_group by $adm : group : $cn");
                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la modification du groupe');
                return $this->render('AmuCliGrouperBundle:Group:groupem.html.twig', array('form' => $form->createView(), 'group' => $group));
            }
            
            // Ferme fichier log
            closelog();
        }
        return $this->render('AmuCliGrouperBundle:Group:groupem.html.twig', array('form' => $form->createView(), 'group' => $group));
    }

    /**
    * Affichage d'une liste de groupe en session
    *
    * @Route("/afficheliste/{opt}/{uid}",name="group_display")
    */
    public function displayAction(Request $request, $opt='search', $uid='') {
    
        // Récupération des groupes mis en session
        $groups = $this->container->get('request')->getSession()->get('groups');

        return $this->render('AmuCliGrouperBundle:Group:recherchegroupe.html.twig',array('groups' => $groups, 'opt' => $opt, 'uid' => $uid));   
    }
    
    /**
    * Gestion des groupes privés de l'utilisateur
    *
    * @Route("/private",name="private_group")
    * @Template() 
    */
    public function privateAction() {
        // Récupération uid de l'utilisateur logué
        $uid = $this->container->get('request')->getSession()->get('login');
        // Recherche des groupes dans le LDAP
        $arData=$this->getLdap()->arDatasFilter("(&(objectClass=groupofNames)(cn=".$uid.":*))",array("cn","description"));
    
        $groups = new ArrayCollection();
        for ($i=0; $i<$arData["count"]; $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]["cn"][0]);
            $groups[$i]->setDescription($arData[$i]["description"][0]);
        }
        // Affichage du tableau de groupes privés via fichier twig
        return array('groups' => $groups, 'nb_groups' => $arData["count"]);
    }
    
    /**
     * Mettre à jour les membres d'un groupe privé.
     *
     * @Route("/private/update/{cn}", name="private_group_update")
     * @Template("AmuCliGrouperBundle:Group:privateupdate.html.twig")
     */
    public function privateupdateAction(Request $request, $cn)
    {
        // Initialisation des entités
        $group = new Group();
        $group->setCn($cn);
        $members = new ArrayCollection();
        
        // Groupe initial pour détecter les modifications
        $groupini = new Group();
        $groupini->setCn($cn);
        $membersini = new ArrayCollection();
        
        // Recherche des membres dans le LDAP
        $arUsers = $this->getLdap()->getMembersGroup($cn.",ou=private");
        
        // Affichage des membres  
        for ($i=0; $i<$arUsers["count"]; $i++) {                     
            $members[$i] = new Member();
            $members[$i]->setUid($arUsers[$i]["uid"][0]);
            $members[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            $members[$i]->setMail($arUsers[$i]["mail"][0]);
            $members[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
            $members[$i]->setMember(TRUE);
            $members[$i]->setAdmin(FALSE);
           
            // Idem pour groupini
            $membersini[$i] = new Member();
            $membersini[$i]->setUid($arUsers[$i]["uid"][0]);
            $membersini[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            $membersini[$i]->setMail($arUsers[$i]["mail"][0]);
            $membersini[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
            $membersini[$i]->setMember(TRUE);
            $membersini[$i]->setAdmin(FALSE);
        }
        // on remplit les groupes
        $group ->setMembers($members);
        $groupini ->setMembers($membersini);
                      
        // Création du formulaire de mise à jour
        $editForm = $this->createForm(new PrivateGroupEditType(), $group);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            // Récupération des données du formulaire
            $groupupdate = new Group();
            $groupupdate = $editForm->getData();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            $adm = $request->getSession()->get('login');
            
            // Récup des appartenances
            $m_update = new ArrayCollection();      
            $m_update = $groupupdate->getMembers();
            
            // Nombre de membres
            $nb_memb = sizeof($m_update);
            
            // Mise à jour des membres et admins
            for ($i=0; $i<sizeof($m_update); $i++){
                $memb = $m_update[$i];
                $membi = $membersini[$i];
                $dn_group = "cn=" . $cn . ", ou=private, ou=groups, dc=univ-amu, dc=fr";
                $u = $memb->getUid();
                
                // Traitement des membres
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMember() != $membi->getMember()) {
                    if ($memb->getMember()) {
                        $r = $this->getLdap()->addMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $u ");
                            $nb_memb++;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $this->getLdap()->delMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $u ");
                            $nb_memb--;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $u ");
                        }
                    }
                }
            }
            // Ferme fichier de log
            closelog();
            
            // Message de notification
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');
            $this->getRequest()->getSession()->set('_saved',1);
            
            // Récupération du nouveau groupe modifié pour affichage
            $newgroup = new Group();
            $newgroup->setCn($cn);
            $newmembers = new ArrayCollection();

            // Recherche des membres dans le LDAP
            $narUsers = $this->getLdap()->getMembersGroup($cn.",ou=private");

            // Affichage des membres  
            for ($i=0; $i<$narUsers["count"]; $i++) {                     
                $newmembers[$i] = new Member();
                $newmembers[$i]->setUid($narUsers[$i]["uid"][0]);
                $newmembers[$i]->setDisplayname($narUsers[$i]["displayname"][0]);
                $newmembers[$i]->setMail($narUsers[$i]["mail"][0]);
                $newmembers[$i]->setTel($narUsers[$i]["telephonenumber"][0]);
                $newmembers[$i]->setMember(TRUE); 
                $newmembers[$i]->setAdmin(FALSE);
            }               
            $newgroup ->setMembers($newmembers);
            
            // Nouveau formulaire avec les infos mises à jour
            $editForm = $this->createForm(new PrivateGroupEditType(), $newgroup);
            
            return array(
            'group'      => $newgroup,
            'nb_membres' => $narUsers["count"],
            'form'   => $editForm->createView()
            );
        }
        else {
            $this->getRequest()->getSession()->set('_saved',0);
            
            return array(
            'group'      => $group,
            'nb_membres' => $arUsers["count"],
            'form'   => $editForm->createView()
            ); 
        }
    }
    
    /**
     * Mettre à jour les membres d'un groupe 
     *
     * @Route("/update/{cn}/{liste}", name="group_update")
     * @Template("AmuCliGrouperBundle:Group:update.html.twig")
     */
    public function updateAction(Request $request, $cn, $liste)
    {
        $group = new Group();
        $group->setCn($cn);
        $members = new ArrayCollection();
        
        // Groupe initial pour détecter les modifications
        $groupini = new Group();
        $groupini->setCn($cn);
        $membersini = new ArrayCollection();
        
        // Récup du filtre amugroupfilter pour affichage
        $amugroupfilter = $this->getLdap()->getAmuGroupFilter($cn);
        if ($amugroupfilter!=false)
            $group->setAmugroupfilter($amugroupfilter);
               
        // Recherche des membres dans le LDAP
        $arUsers = $this->getLdap()->getMembersGroup($cn);
        
        // Recherche des admins dans le LDAP
        $arAdmins = $this->getLdap()->getAdminsGroup($cn);
        $flagMembers = array();
        for($i=0;$i<$arAdmins[0]["amugroupadmin"]["count"];$i++)
            $flagMembers[] = FALSE;
        
        // Affichage des membres  
        for ($i=0; $i<$arUsers["count"]; $i++) {                     
            $members[$i] = new Member();
            $members[$i]->setUid($arUsers[$i]["uid"][0]);
            $members[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            $members[$i]->setMail($arUsers[$i]["mail"][0]);
            $members[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
            $members[$i]->setMember(TRUE);
            $members[$i]->setAdmin(FALSE);
           
            // Idem pour groupini
            $membersini[$i] = new Member();
            $membersini[$i]->setUid($arUsers[$i]["uid"][0]);
            $membersini[$i]->setDisplayname($arUsers[$i]["displayname"][0]);
            $membersini[$i]->setMail($arUsers[$i]["mail"][0]);
            $membersini[$i]->setTel($arUsers[$i]["telephonenumber"][0]);
            $membersini[$i]->setMember(TRUE);
            $membersini[$i]->setAdmin(FALSE);
            
            // on teste si le membre est aussi admin
            for ($j=0; $j<$arAdmins[0]["amugroupadmin"]["count"]; $j++) {
                $uid = preg_replace("/(uid=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", $arAdmins[0]["amugroupadmin"][$j]);
                if ($uid==$arUsers[$i]["uid"][0]) {
                    $members[$i]->setAdmin(TRUE);
                    $membersini[$i]->setAdmin(TRUE);
                    $flagMembers[$j] = TRUE;
                    break;
                }
            }           
        }
                
        // Affichage des admins qui ne sont pas membres
        for ($j=0; $j<$arAdmins[0]["amugroupadmin"]["count"]; $j++) {       
            if ($flagMembers[$j]==FALSE) {
                // si l'admin n'est pas membre du groupe, il faut aller récupérer ses infos dans le LDAP
                $uid = preg_replace("/(uid=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", $arAdmins[0]["amugroupadmin"][$j]);
                $result = $this->getLdap()->arUserInfos($uid, array("uid", "sn", "displayname", "mail", "telephonenumber"));
                
                $memb = new Member();
                $memb->setUid($result["uid"]);
                $memb->setDisplayname($result["displayname"]);
                $memb->setMail($result["mail"]);
                $memb->setTel($result["telephonenumber"]);
                $memb->setMember(FALSE);
                $memb->setAdmin(TRUE);
                $members[] = $memb;
                
                // Idem pour groupini
                $membini = new Member();
                $membini->setUid($result["uid"]);
                $membini->setDisplayname($result["displayname"]);
                $membini->setMail($result["mail"]);
                $membini->setTel($result["telephonenumber"]);
                $membini->setMember(FALSE);
                $membini->setAdmin(TRUE);
                $membersini[] = $membini;
            }
        }
        
        $group ->setMembers($members);
        $groupini ->setMembers($membersini);
                      
        // Création du formulaire de mise à jour du groupe
        $editForm = $this->createForm(new GroupEditType(), $group);    
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $groupupdate = new Group();
            $groupupdate = $editForm->getData();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            $adm = $request->getSession()->get('login');
            
            $m_update = new ArrayCollection();      
            $m_update = $groupupdate->getMembers();
            
            $nb_memb = sizeof($m_update);
            
            // on parcourt tous les membres
            for ($i=0; $i<sizeof($m_update); $i++)
            {
                $memb = $m_update[$i];
                $membi = $membersini[$i];
                $dn_group = "cn=" . $cn . ", ou=groups, dc=univ-amu, dc=fr";
                $u = $memb->getUid();
                
                // Traitement des membres
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMember() != $membi->getMember()) {
                    if ($memb->getMember()) {
                        $r = $this->getLdap()->addMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $u ");
                            $nb_memb++;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $this->getLdap()->delMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $u ");
                            $nb_memb--;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $u ");
                        }
                    }
                }
                // Traitement des admins
                // Idem : si changement, on répercute dans le ldap
                if ($memb->getAdmin() != $membi->getAdmin()) {
                    if ($memb->getAdmin()) {
                        $r = $this->getLdap()->addAdminGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_admin by $adm : group : $cn, user : $u ");
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $this->getLdap()->delAdminGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_admin by $adm : group : $cn, user : $u ");
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $cn, user : $u ");
                        }
                    }
                }
            }
            // Ferme fichier de log
            closelog();
            
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');       
            $this->getRequest()->getSession()->set('_saved',1);
            
            // Récupération du nouveau groupe modifié pour affichage
            $newgroup = new Group();
            $newgroup->setCn($cn);
            $newmembers = new ArrayCollection();

            // Recherche des membres dans le LDAP
            $narUsers = $this->getLdap()->getMembersGroup($cn);
            
            // Recherche des admins dans le LDAP
            $narAdmins = $this->getLdap()->getAdminsGroup($cn);
            $nflagMembers = array();
            for($i=0;$i<$narAdmins[0]["amugroupadmin"]["count"];$i++)
                $nflagMembers[] = FALSE;

            // Affichage des membres  
            for ($i=0; $i<$narUsers["count"]; $i++) {                     
                $newmembers[$i] = new Member();
                $newmembers[$i]->setUid($narUsers[$i]["uid"][0]);
                $newmembers[$i]->setDisplayname($narUsers[$i]["displayname"][0]);
                $newmembers[$i]->setMail($narUsers[$i]["mail"][0]);
                $newmembers[$i]->setTel($narUsers[$i]["telephonenumber"][0]);
                $newmembers[$i]->setMember(TRUE); 
                $newmembers[$i]->setAdmin(FALSE);
                
                // on teste si le membre est aussi admin
                for ($j=0; $j<$narAdmins[0]["amugroupadmin"]["count"]; $j++) {
                    $uid = preg_replace("/(uid=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", $narAdmins[0]["amugroupadmin"][$j]);
                    if ($uid==$narUsers[$i]["uid"][0]) {
                        $newmembers[$i]->setAdmin(TRUE);
                        $nflagMembers[$j] = TRUE;
                        break;
                    }
                }
            }
            // Affichage des admins qui ne sont pas membres
            for ($j=0; $j<$narAdmins[0]["amugroupadmin"]["count"]; $j++) {       
                if ($nflagMembers[$j]==FALSE)
                {
                    // si l'admin n'est pas membre du groupe, il faut aller récupérer ses infos dans le LDAP
                    $uid = preg_replace("/(uid=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", $narAdmins[0]["amugroupadmin"][$j]);
                    $result = $this->getLdap()->arUserInfos($uid, array("uid", "sn", "displayname", "mail", "telephonenumber"));

                    $nmemb = new Member();
                    $nmemb->setUid($result["uid"]);
                    $nmemb->setDisplayname($result["displayname"]);
                    $nmemb->setMail($result["mail"]);
                    $nmemb->setTel($result["telephonenumber"]);
                    $nmemb->setMember(FALSE);
                    $nmemb->setAdmin(TRUE);
                    $newmembers[] = $nmemb;
                }
            }
                            
            $newgroup ->setMembers($newmembers);
            
            // Création du formulaire de mise à jour du groupe
            $editForm = $this->createForm(new GroupEditType(), $newgroup);
            
            return array(
            'group'      => $newgroup,
            'nb_membres' => $narUsers["count"],
            'form'   => $editForm->createView(),
            'liste' => $liste    
            );
        }
        else {
           $this->getRequest()->getSession()->set('_saved',0);
            
            return array(
            'group'      => $group,
            'nb_membres' => $arUsers["count"],
            'form'   => $editForm->createView(),
            'liste' => $liste
            );
        }
    }
    
    /** 
    * Affichage du document d'aide
    *
    * @Route("/aide",name="aide")
    */
    public function aideAction() {
        return $this->render('AmuCliGrouperBundle:Group:aide.html.twig');
    }
    
    /** 
    * Affichage du document d'aide concernant les groupes privés
    *
    * @Route("/aide_priv",name="aide_priv")
    */
    public function aideprivAction() {
        return $this->render('AmuCliGrouperBundle:Group:aidepriv.html.twig');
    }
    
}