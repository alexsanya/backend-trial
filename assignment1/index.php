<?php

// Load database connection, helpers, etc.
require_once(__DIR__ . '/errors.php');
require_once(__DIR__ . '/include.php');

// Vars
$period = 12; // Life-Time of 12 months
$commission = 0.10; // 10% commission

// Prepare query
$result = $db
	->prepare('
		select distinct bookings.booker_id as bookerId,
				bookingitems.end_timestamp as bookingDate,
				(spaces.hour_price*(bookingitems.end_timestamp-bookingitems.start_timestamp)/3600) as price
		from bookingitems
		join items
		on bookingitems.item_id=items.id
		join bookings on bookingitems.booking_id=bookings.id
		join spaces
		on spaces.item_id=items.id
		order by bookingitems.end_timestamp
	')
	->run();


?>
<!doctype html>
<html>
	<head>
		<title>Assignment 1: Create a Report (SQL)</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<style type="text/css">
			.report-table
			{
				width: 100%;
				border: 1px solid #000000;
			}
			.report-table td,
			.report-table th
			{
				text-align: left;
				border: 1px solid #000000;
				padding: 5px;
			}
			.report-table .right
			{
				text-align: right;
			}
		</style>
	</head>
	<body>
		<h1>Report:</h1>
		<table class="report-table">
			<thead>
				<tr>
					<th>Start</th>
					<th>Bookers</th>
					<th># of bookings (avg)</th>
					<th>Turnover (avg)</th>
					<th>LTV</th>
				</tr>
			</thead>
			<tbody>
				<?php
				date_default_timezone_set('America/Los_Angeles');
				$monthLTVReport = [];
				$numberOfBookings = [];
				$priceOfBookings = [];
				$firstBooking = [];
				$bookingsLog = [];

				function getMonthLTV($bookersList){
					global $numberOfBookings;
					global $priceOfBookings;
					$count=0;
					$totalSum=0;
					foreach ($bookersList as  $bookerId):
						$totalSum += $priceOfBookings[$bookerId];
						$count += $numberOfBookings[$bookerId];
					endforeach;
					return [
						'turnoverAvg' => $totalSum / $count,
						'bookingsAvg' => $count / count($bookersList)
					];
				}

				foreach ($result as $index => $row):
					$bookingsLog[] = $row;
					$year = date('Y', $row->bookingDate);
					$month = date('m', $row->bookingDate);
					if (array_key_exists($row->bookerId, $numberOfBookings)) {
						$bookingDateTime = new DateTime();
						$bookingDateTime->setTimestamp($row->bookingDate);
						$firstBookingDateTime = new DateTime();
						$firstBookingDateTime->setTimestamp($firstBooking[$row->bookerId]);
						if (date_diff($bookingDateTime, $firstBookingDateTime)->m <= $period ) {
							$numberOfBookings[$row->bookerId]++;
							$priceOfBookings[$row->bookerId] += $row->price;
						}
						continue;
					};
					$numberOfBookings[$row->bookerId] = 1;
					$priceOfBookings[$row->bookerId]=$row->price;
					$firstBooking[$row->bookerId]=$row->bookingDate;
					if (array_key_exists($year, $monthLTVReport)) {
						if (array_key_exists($month, $monthLTVReport[$year])) {
							$monthLTVReport[$year][$month][] = $row->bookerId;
						} else {
							$monthLTVReport[$year][$month] = [$row->bookerId];
						}
					} else {
						$monthLTVReport[$year] = [];
						$monthLTVReport[$year][$month] = [$row->bookerId];
					}
					?>

				<?php endforeach;
				foreach ($monthLTVReport as $yearNumber => $months) :
					foreach ($months as $monthNumber => $bookers):
						$singleMonthLTV = getMonthLTV($bookers);
					?>
						<tr>
							<td><?= $monthNumber ?>&nbsp;<?= $yearNumber ?></td>
							<td><?= count($bookers) ?></td>
							<td><?= $singleMonthLTV['bookingsAvg'] ?></td>
							<td><?= $singleMonthLTV['turnoverAvg'] ?></td>
							<td><?= $singleMonthLTV['turnoverAvg']*$commission ?></td>
						</tr>
					<?php
					endforeach;
				endforeach;
				?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4" class="right"><strong>Total rows:</strong></td>
					<td><?= $index + 1 ?></td>
				</tr>
			</tfoot>
		</table>
	</body>
</html>