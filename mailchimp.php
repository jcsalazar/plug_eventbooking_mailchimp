<?php
/**
 * @version            2.4.1
 * @package            Joomla
 * @subpackage         Event Booking
 * @author             Tuan Pham Ngoc
 * @copyright          Copyright (C) 2010 - 2016 Ossolution Team
 * @license            GNU/GPL, see LICENSE.php
 */

// no direct access
defined('_JEXEC') or die;

class plgEventBookingMailchimp extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		JFactory::getLanguage()->load('plg_eventbooking_mailchimp', JPATH_ADMINISTRATOR);
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_eventbooking/table');
	}

	/**
	 * Render settings form
	 *
	 * @param $row
	 *
	 * @return array
	 */
	public function onEditEvent($row)
	{
		ob_start();
		$this->drawSettingForm($row);
		$form = ob_get_contents();
		ob_end_clean();

		return array('title' => JText::_('PLG_EB_MAILCHIMP_SETTINGS'),
		             'form'  => $form
		);
	}

	/**
	 * Store setting into database, in this case, use params field of plans table
	 *
	 * @param event   $row
	 * @param Boolean $isNew true if create new plan, false if edit
	 */
	public function onAfterSaveEvent($row, $data, $isNew)
	{
		// $row of table EB_plans
		$params = new JRegistry($row->params);
		$params->set('mailchimp_list_ids', implode(',', $data['mailchimp_list_ids']));
		$row->params = $params->toString();

		$row->store();
	}

	/**
	 * Run when registration record stored to database
	 *
	 * @param JTable $row
	 */
	public function onAfterStoreRegistrant($row)
	{
		$event = JTable::getInstance('EventBooking', 'Event');
		$event->load($row->event_id);
		$params  = new JRegistry($event->params);
		$listIds = 'f40eb1f47e';		/* Get Event Location*/		$location = JTable::getInstance('EventBooking', 'Location');		$location->load($event->location_id);				/* Get Instructor Email From Created_By on event*/		$db               = JFactory::getDbo();		$query            = $db->getQuery(true);				$query->clear();		$query->select('a.*')				->from('#__users AS a')				->innerJoin('#__eb_events AS b ON a.id = b.created_by')				->where('b.id=' . $row->event_id);				$db->setQuery($query);				$rowuser = $db->loadObject();				
		if ($listIds != '')
		{
			$listIds = explode(',', $listIds);
			if (count($listIds))
			{
				require_once dirname(__FILE__) . '/api/MailChimp.php';
				$mailchimp = new MailChimp($this->params->get('api_key'));				
				foreach ($listIds as $listId)
				{
					if ($listId)
					{
						$mailchimp->call('lists/subscribe', array(
							'id'                => $listId,
							'email'             => array('email' => $row->email),
							'merge_vars'        => array('FNAME' => $row->first_name, 'LNAME' => $row->last_name,'MMERGE3'  => $event->title,'MMERGE4'  => $event->event_date														,'MMERGE5'  => $row->event_id . $row->registration_code														,'MMERGE6'  => 'No'														,'MMERGE7'  => 'No'														,'MMERGE8'  => 'No'														,'MMERGE9'  => $location->name . ' , ' . $location->address . ' , ' . $location->city . ' , ' . $location->state . ' ' . $location->zip 														,'MMERGE10'  => $event->event_date														,'MMERGE11'  => $rowuser->email														),
							'double_optin'      => false,
							'update_existing'   => true,
							'replace_interests' => false,
							'send_welcome'      => false,
						));
					}
				}
			}
		}
	}

	/**
	 * Display form allows users to change settings on event add/edit screen
	 *
	 * @param object $row
	 */
	private function drawSettingForm($row)
	{
		require_once dirname(__FILE__) . '/api/MailChimp.php';
		$mailchimp = new MailChimp($this->params->get('api_key'));
		$lists     = $mailchimp->call('lists/list');
		if ($lists === false)
		{

		}
		else
		{
			$params  = new JRegistry($row->params);
			$listIds = explode(',', $params->get('mailchimp_list_ids', ''));
			$options = array();
			$lists   = $lists['data'];
			if (count($lists))
			{
				foreach ($lists as $list)
				{
					$options[] = JHtml::_('select.option', $list['id'], $list['name']);
				}
			}
			?>
			<table class="admintable adminform" style="width: 90%;">
				<tr>
					<td width="220" class="key">
						<?php echo JText::_('PLG_EB_MAILCHIMP_ASSIGN_TO_LISTS'); ?>
					</td>
					<td>
						<?php echo JHtml::_('select.genericlist', $options, 'mailchimp_list_ids[]', 'class="inputbox" multiple="multiple" size="10"', 'value', 'text', $listIds)?>
					</td>
					<td>
						<?php echo JText::_('PLG_EB_ACYMAILING_ASSIGN_TO_LISTS_EXPLAIN'); ?>
					</td>
				</tr>
			</table>
		<?php
		}
	}
}
