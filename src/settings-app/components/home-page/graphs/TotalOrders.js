import React from 'react';
import { useSettingsStateValue } from '@SettingsApp/utils/StateProvider';
import Chart from 'react-apexcharts';
import { __ } from '@wordpress/i18n';

function TotalOrders( props ) {
	const [ { analyticsData } ] = useSettingsStateValue();
	const totalOrders =
		analyticsData && analyticsData.flow_stats.orders_by_date
			? analyticsData.flow_stats.orders_by_date
			: [];

	const orders = [];

	props.dateRange.map( ( date ) => {
		const orderData = totalOrders.find( ( order ) => {
			return order.OrderDate === date;
		} );

		orders.push( orderData ? orderData.OrderCount : 0 );

		return null;
	} );

	const dates = [];
	props.dateRange.map( ( date ) => {
		const formattedDate = new Date( date );

		dates.push(
			formattedDate.toLocaleDateString( 'en-US', {
				// year: 'numeric',
				month: 'short',
				day: 'numeric',
			} )
		);
		return null;
	} );

	const options = {
		chart: {
			id: 'wcf-analytics-graphs',
			type: 'area',
			// zoom: {
			// 	type: 'x',
			// 	enabled: true,
			// 	autoScaleYaxis: true,
			// },
			toolbar: {
				show: false,
			},
		},
		xaxis: {
			categories: dates,
		},
		stroke: {
			curve: 'smooth',
		},
		tooltip: {
			x: {
				format: 'dd MMM',
			},
		},
		colors: [ '#F06434' ],
	};

	const series = [
		{
			name: 'Total Orders',
			data: orders,
		},
	];

	return (
		<section className="block p-6 justify-between">
			<div className="mr-16 w-full flex items-center">
				<h3 className="flex-1 text-xl font-semibold text-gray-800">
					{ __( 'Total Orders', 'cartflows' ) }
				</h3>
				{ /* <button
					className="bg-white px-2.5 py-1 items-center text-slate-800 text-sm font-medium border border-gray-200 rounded-md focus:outline-none mr-2.5"
					type="button"
				>
					Daily
				</button>
				<button
					className="bg-white px-2.5 py-1 items-center text-slate-600 text-sm font-medium border border-gray-200 rounded-md focus:outline-none"
					type="button"
				>
					Monthly
				</button> */ }
			</div>
			<div className="mt-4">
				<Chart
					options={ options }
					series={ series }
					type="area"
					// width={ auto }
					height={ 305 }
				/>
			</div>
		</section>
	);
}

export default TotalOrders;
