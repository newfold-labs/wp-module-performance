const path = require( 'path' );
const { merge } = require( 'webpack-merge' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const glob = require( 'glob' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );

module.exports = merge( defaultConfig, {
	entry: glob.sync( './scripts/**/*.js' ).reduce( ( entries, file ) => {
		const name = path.basename( file, '.js' ); // Use file name (without extension) as the key
		entries[ name ] = file;
		return entries;
	}, {} ),
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].min.js',
	},
	optimization: {
		minimize: true,
		minimizer: [
			new TerserPlugin( {
				terserOptions: {
					compress: {
						drop_console: true, // Remove console logs
					},
					format: {
						comments: false, // Remove comments
						beautify: false, // Ensure no extra spaces or formatting
					},
				},
				extractComments: false,
			} ),
			new CssMinimizerPlugin(), // Minify CSS
		],
	},
	module: {
		rules: [
			{
				test: /\.css$/i,
				use: [ 'style-loader', 'css-loader' ],
			},
		],
	},
} );
