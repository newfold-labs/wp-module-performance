const path = require( 'path' );
const glob = require( 'glob' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );

module.exports = {
	mode: 'production', // Automatically sets minification
	entry: glob.sync( './assets/**/*.js' ).reduce( ( entries, file ) => {
		const name = path.relative( './assets', file ).replace( /\.js$/, '' );
		entries[ name ] = file;
		return entries;
	}, {} ),
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].min.js',
	},
	module: {
		rules: [
			{
				test: /\.css$/i,
				use: [
					MiniCssExtractPlugin.loader, // Extract CSS into files
					'css-loader', // Resolve CSS imports
				],
			},
		],
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
						comments: false,
					},
				},
				extractComments: false, // Avoid .LICENSE.txt files
			} ),
			new CssMinimizerPlugin(), // Minify CSS
		],
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: '[name].min.css', // Output for CSS files
		} ),
	],
	resolve: {
		extensions: [ '.js', '.css' ], // Allow importing JS and CSS without extensions
	},
};
