<script>
	if ('permissions' in navigator) {
		navigator.permissions.query({
			name: 'local-network-access'
		}).then(status => {
			console.log(`Local network access permission status: ${status.state}`); // 'granted', 'denied', or 'prompt'
			status.onchange = () => {
				console.log(`Local network access permission status changed to ${status.state}`);
			};
		}).catch(error => {
			console.error('Permissions API query for local-network-access failed:', error);
		});
	} else {
		console.log('Permissions API or local-network-access permission not supported in this browser.');
	}
</script>