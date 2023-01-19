import classnames from 'classnames';
import icons from './icons.js';

const { __ } = wp.i18n;
const { registerBlockType, getBlockDefaultClassName } = wp.blocks;
const { RichText, MediaPlaceholder, MediaUpload, InspectorControls, BlockControls, BlockAlignmentToolbar } = wp.blockEditor;
const { Fragment } = wp.element;
const { withNotices, Button, TextControl, TextareaControl, PanelBody, ToolbarGroup, ToolbarButton, ResizableBox } = wp.components;
const { withState } = wp.compose;
const { isBlobURL } = wp.blob;

const isExternalPDF = (id, url) => url && !id && !isBlobURL(url);

const renderEmbed = (props) => {
	const { attributes: { title, description, url, width, height, align } } = props;
	const style = { width, height };
	const myClassName = getBlockDefaultClassName('file-embedder/pdf');

	if (undefined === url || !url) {
		return null;
	}

	return (
		<figure className={`${myClassName}__content-wrapper align${align}`}>
			<object
				className="file-embedder"
				data={url + '#scrollbar=1&toolbar=1'}
				type="application/pdf"
				height={style.height}
				width={style.width}
				title={description}
			>
			</object>
			<iframe
				className="file-embedder"
				src={'https://docs.google.com/viewer?url=' + encodeURIComponent(url) + '&embedded=true'}
				frameBorder="0"
				height={style.height}
				width={style.width}
				title={description}
			>
			</iframe>
		</figure>
	);
};

const renderEdit = (props) => {
	const { attributes: { id, title, description, url, width, height, align }, setAttributes, isEditing, hasError, setState, className, media, noticeUI, noticeOperations, toggleSelection, isRTL } = props;
	const isExternal = isExternalPDF(id, url);

	function updateAttribute(key) {
		return function (value) {
			const attr = {};
			attr[key] = value;
			setAttributes(attr);
		}
	}

	function onSelectFile(media) {
		if (media && media.url) {
			setState({
				hasError: false,
			});
			setAttributes({
				url: media.url,
				id: media.id,
				title: media.title,
				description: media.description,
			});
		}
	}

	function toggleIsEditing() {
		setState({
			isEditing: !isEditing,
		});
	}

	if (undefined === url || !url || hasError || isEditing) {
		return (
			<MediaPlaceholder
				icon={icons.pdf}
				labels={{
					title: __('PDF', 'file-embedder'),
					instructions: __('Drag a PDF, upload a new one or select a PDF from your library.', 'file-embedder'),
				}}
				onSelect={onSelectFile}
				onSelectURL={updateAttribute('url')}
				notices={props.noticeUI}
				onError={props.noticeOperations.createErrorNotice}
				accept='application/pdf'
				allowedTypes={['application/pdf']}
			/>
		);
	}


	const classes = classnames(className, {
		'is-transient': isBlobURL(url),
	});


	let showRightHandle = false;
	let showLeftHandle = false;


	if (align === 'center') {

		showRightHandle = true;
		showLeftHandle = true;
	} else if (isRTL) {

		if (align === 'left') {
			showRightHandle = true;
		} else {
			showLeftHandle = true;
		}
	} else {

		if (align === 'right') {
			showLeftHandle = true;
		} else {
			showRightHandle = true;
		}
	}

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('File Embedder', 'file-embedder')} initialOpen={true}>
					<div>
						<TextareaControl
							label={__('Long Description (optional)', 'file-embedder')}
							value={undefined === description ? '' : description}
							onChange={updateAttribute('description')}
							help={__('Long Description used for `title` tag and accessibility.', 'file-embedder')}
						/>
						<TextControl
							type="number"
							min={20}
							label={__('Width', 'file-embedder')}
							value={undefined === width ? FileEmbedder.attributes.width.default : width}
							onChange={updateAttribute('width')}
						/>
						<TextControl
							type="number"
							label={__('Height', 'file-embedder')}
							value={undefined === height ? fileEmbedder.attributes.height.default : height}
							min={1}
							onChange={updateAttribute('height')}
						/>
					</div>
				</PanelBody>
			</InspectorControls>

			<BlockControls>
				<BlockAlignmentToolbar
					value={align}
					onChange={updateAttribute('align')}
					controls={['left', 'center', 'right']}
				/>
				<ToolbarGroup>
					{isExternal && (
						<ToolbarButton
							className="components-icon-button"
							label={__('Edit PDF', 'file-embedder')}
							onClick={toggleIsEditing}
							icon="edit"
						/>
					)}
					{!isExternal && (
						<MediaUpload
							onSelect={onSelectFile}
							value={id}
							render={({ open }) => (
								<Button
									className="components-toolbar__control"
									label={__('Edit PDF', 'file-embedder')}
									onClick={open}
									icon="edit"
								/>
							)}
						/>
					)}
				</ToolbarGroup>
			</BlockControls>

			<div className={classes}>
				<ResizableBox
					size={
						width && height ? {
							width,
							height,
						} : undefined
					}
					lockAspectRatio
					enable={{
						top: false,
						right: showRightHandle,
						bottom: true,
						left: showLeftHandle,
					}}
					onResizeStart={() => {
						toggleSelection(false);
					}}
					onResizeStop={(event, direction, elt, delta) => {
						setAttributes({
							width: parseInt(width + delta.width, 10),
							height: parseInt(height + delta.height, 10),
						});
						toggleSelection(true);
					}}
				>
					{renderEmbed(props)}
				</ResizableBox>
			</div>
		</Fragment>
	);
};

let embedPDFViewer = registerBlockType('file-embedder/pdf', {
	title: __('PDF', 'file-embedder'),
	icon: icons.pdf,
	category: 'embed',
	attributes: {
		id: { type: 'number', },
		title: { type: 'string' },
		description: {
			type: 'string',
			default: '',
		},
		url: { type: 'string', },
		width: {
			type: 'string',
			default: 600,
		},
		height: {
			type: 'string',
			default: 600,
		},
		align: {
			type: 'string',
			default: 'center',
		},
		supports: {
			align: ['left', 'center', 'right'],
		},
	},

	getEditWrapperProps(attributes) {
		const { align, width } = attributes;
		if ('left' === align || 'center' === align || 'right' === align) {
			return { 'data-align': align, 'data-resized': !!width };
		}
	},

	edit: withNotices(withState({ isEditing: false, hasError: false })(renderEdit)),
	save: renderEmbed,
});
