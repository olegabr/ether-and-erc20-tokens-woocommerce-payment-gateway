module.exports = function(grunt) {

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		uglify: {
			epgjs: {
				files: {
			        'js/ether-and-erc20-tokens-woocommerce-payment-gateway.min.js': ['js/ether-and-erc20-tokens-woocommerce-payment-gateway.js']
			        , 'js/qrcode.min.js': ['js/qrcode.js']
			        , 'js/jquery.qrcode.min.js': ['js/jquery.qrcode.js']
			    }
			}
		},
		watch: {
			js: {
				files: [
					'js/ether-and-erc20-tokens-woocommerce-payment-gateway.js'
					, 'js/qrcode.js'
					, 'js/jquery.qrcode.js'
				],
				tasks: [ 'uglify' ]
			},
		}
	});

	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-watch');

	// Default task(s).
	grunt.registerTask('default', ['uglify']);

};
