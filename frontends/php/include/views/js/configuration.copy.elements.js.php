<script type="text/javascript">
	jQuery(function($) {
		$('#copy_type').on('change', function () {
			var copy_type = $('input[name=copy_type]:checked', $('#copy_type')).val();
			$('#host_groups_row').toggleClass('hidden', (copy_type != '<?= COPY_TYPE_TO_HOST_GROUP ?>'));
			$('#hosts_row').toggleClass('hidden', (copy_type != '<?= COPY_TYPE_TO_HOST ?>'));
			$('#templates_row').toggleClass('hidden', (copy_type != '<?= COPY_TYPE_TO_TEMPLATE ?>'));
		});
	});
</script>
