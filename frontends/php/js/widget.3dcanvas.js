/*
 ** Zabbix
 ** Copyright (C) 2001-2019 Zabbix SIA
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/


;(function ($) {

var __log = console['log'];

var widgets_canvas = {},
	raycaster = new THREE.Raycaster(),
	clock = new THREE.Clock(),
	geometry = {
		sphere: new THREE.SphereBufferGeometry(1, 70, 70, 0, Math.PI * 2, 0, Math.PI * 2),
		icosahedron: new THREE.IcosahedronBufferGeometry(1.5, 5),
		connection: new THREE.IcosahedronBufferGeometry(1, 1)
	},
	material = {
		connection: new THREE.MeshBasicMaterial({color: 0x0275ff}),
		red: new THREE.MeshStandardMaterial({color: 0xee0808, flatShading: true}),
		green: new THREE.MeshStandardMaterial({color: 0x08ee08, flatShading: true}),
		blue: new THREE.MeshStandardMaterial({color: 0x0a466a, flatShading: true}),
		white: new THREE.MeshStandardMaterial({color: 0xffffff, flatShading: true}),
		inner_sphere: new THREE.MeshStandardMaterial({color: 0x0275b8})
	},
	shaders = getShadersGLSL();

	var  ENTIRE_SCENE = 0, BLUR_SCENE = 1;
// var ENTIRE_SCENE = 0, BLOOM_SCENE = 1;
// var bloomLayer = new THREE.Layers();
// bloomLayer.set( BLOOM_SCENE );

$.subscribe('init.widget.3dcanvas', initWidget3dCanvasHandler);
animate();

/**
 * Initialize canvas object.
 *
 * @param {Object} ev        jQuery event object.
 */
function initWidget3dCanvasHandler(ev) {
	__log('init.widget.3dcanvas', arguments);
	// find by unique id passed in ev.widgetid field
	var container = $('[data-3d-canvas]').first();
	var widgetid = '1234';

	widgets_canvas[widgetid] = init(container);
	widgets_canvas[widgetid].mouse = {x: 0, y:0, clock: 0};
	widgets_canvas[widgetid].labels = [];

	fillScene(widgets_canvas[widgetid], ev.scene, pointsDistributionSphere);

	container.on('mousemove', {widgetid: widgetid}, containerMouseMoveHandler);
	container.on('mouseout', {widgetid: widgetid}, containerMouseOutHandler);
	container.on('click', {widgetid: widgetid}, containerMouseClickHandler);
}

/**
 * Updates widget mouse position to scene.
 *
 * @param {Object} ev         jQuery event object.
 */
function containerMouseMoveHandler(ev) {
	ev.preventDefault();

	var widgetid = ev.data.widgetid,
		container = $(ev.target),
		rect = ev.target.getBoundingClientRect();

	widgets_canvas[widgetid].mouse = {
		x: ((ev.clientX - rect.left) / container.width()) * 2 - 1,
		y: - ((ev.clientY - rect.top) / container.height()) * 2 + 1,
		clock: (new Date).getTime()
	}
}

function containerMouseClickHandler(ev) {
	ev.preventDefault();

	var widgetid = ev.data.widgetid,
		container = $(ev.target),
		rect = ev.target.getBoundingClientRect(),
		scene = widgets_canvas[widgetid];

	scene.mouse = {
		x: ((ev.clientX - rect.left) / container.width()) * 2 - 1,
		y: - ((ev.clientY - rect.top) / container.height()) * 2 + 1,
		clock: (new Date).getTime()
	}

	raycaster.setFromCamera(scene.mouse, scene.camera);
	$.each(raycaster.intersectObjects(scene.scene.children), function(_, o) {
		if (o.object && 'mouseclick' in o.object) {
			scene.intersected = o;
			o.object.mouseclick(scene);

			return false;
		}
	});
}

function containerMouseOutHandler(ev) {

}

/**
 * Initialize three.js scene.
 *
 * @param {Object} container   Dom element, 3d canvas container.
 */
function init(container) {
	var width = container.width(),
		height = container.height();
	var camera = new THREE.PerspectiveCamera(60, width / height, 0.1, 1000);
	var renderer = new THREE.WebGLRenderer({ alpha: true });
	var controls = new THREE.OrbitControls(camera, renderer.domElement);
	var scene = new THREE.Scene();
	// scene.background = new THREE.Color( 0xffff00 );

	var light_hemisphere = new THREE.HemisphereLight(0xffffff, 0x444444);
	light_hemisphere.position.set(0, 1000, 0);
	scene.add(light_hemisphere);
	var light_directional = new THREE.DirectionalLight(0xffffff, 0.8);
	light_directional.position.set(-3000, 1000, -1000);
	scene.add(light_directional);

	camera.position.set(0, 0, 1000);
	camera.lookAt(0, 0, 0);
	controls.update();

	renderer.shadowMap.enabled = true;
	renderer.setPixelRatio(window.devicePixelRatio);
	renderer.setSize(width, height - 10);
	container.append(renderer.domElement);
	// var materials = {};
	// var darkMaterial = new THREE.MeshBasicMaterial( { color: "white" } );

	// Enable camera auto rotation
	controls.target = new THREE.Vector3(3, 7, 0);
	controls.update();
	controls.autoRotate = true;
	controls.autoRotateSpeed = 3.0;

	// var scene_pass = new THREE.RenderPass(scene, camera);
	// var params = {
	// 	exposure: 1,
	// 	bloomStrength: 5,
	// 	bloomThreshold: 0,
	// 	bloomRadius: 0,
	// 	scene: "Scene with Glow"
	// };
	// var bloom = new THREE.UnrealBloomPass(new THREE.Vector2(width, height), 1.5, 0.4, 0.85);
	// bloom.threshold = params.threshold;
	// bloom.strength = params.strength;
	// bloom.radius = params.radius;
	// var bloom_composer = new THREE.EffectComposer( renderer );
	// bloom_composer.renderToScreen = false;
	// bloom_composer.addPass(scene_pass);
	// bloom_composer.addPass(bloom);
	// var final_pass = new THREE.ShaderPass(
	// 	new THREE.ShaderMaterial({
	// 		uniforms: {
	// 			baseTexture: {value: null},
	// 			bloomTexture: {value: bloom_composer.renderTarget2.texture},
	// 			vertexShader: shaders.bloomVertexShader,
	// 			fragmentShader: shaders.bloomFragmentShader,
	// 			defines: {}
	// 		}
	// 	}), "baseTexture"
	// );
	// final_pass.needSwap = true;
	// var final_composer = new THREE.EffectComposer(renderer);
	// final_composer.addPass(scene_pass);
	// final_composer.addPass(bloom);

	// debug
	// var gui = new GUI();
	// var folder = gui.addFolder( 'Bloom Parameters' );
	// folder.add( params, 'exposure', 0.1, 2 ).onChange( function ( value ) {
	// 	renderer.toneMappingExposure = Math.pow( value, 4.0 );
	// 	render();
	// } );
	// folder.add(params, 'bloomThreshold', 0.0, 1.0 ).onChange( function ( value ) {
	// 	bloom.threshold = Number( value );
	// 	render();
	// } );
	// folder.add( params, 'bloomStrength', 0.0, 10.0 ).onChange( function ( value ) {
	// 	bloom.strength = Number( value );
	// 	render();
	// } );
	// folder.add( params, 'bloomRadius', 0.0, 1.0 ).step( 0.01 ).onChange( function ( value ) {
	// 	bloom.radius = Number( value );
	// 	render();
	// } );

	// function render() {
	// 	scene.traverse( darkenNonBloomed );
	// 	bloom_composer.render();
	// 	scene.traverse( restoreMaterial );
	// }

	// function darkenNonBloomed( obj ) {
	// 	if ( obj.isMesh && bloomLayer.test( obj.layers ) === false ) {
	// 		materials[ obj.uuid ] = obj.material;
	// 		obj.material = darkMaterial;
	// 	}
	// }
	// function restoreMaterial( obj ) {
	// 	if ( materials[ obj.uuid ] ) {
	// 		obj.material = materials[ obj.uuid ];
	// 		delete materials[ obj.uuid ];
	// 	}
	// }

	var render_params = {
		minFilter: THREE.LinearFilter,
		magFilter: THREE.LinearFilter,
		stencilBuffer: false
	};
	var composer = new THREE.EffectComposer(renderer);
	var render_pass = new THREE.RenderPass(scene, camera);
	var save_pass = new THREE.SavePass(
		new THREE.WebGLRenderTarget(
			width,
			height,
			render_params
		)
	);
	var blend_pass = new THREE.ShaderPass(THREE.BlendShader, "tDiffuse1");
	blend_pass.uniforms.tDiffuse2.value = save_pass.renderTarget.texture;
	blend_pass.uniforms.mixRatio.value = 0.9;
	var output_pass = new THREE.ShaderPass(THREE.CopyShader);
	output_pass.renderToScreen = true;
	composer.addPass(render_pass);
	composer.addPass(blend_pass);
	composer.addPass(save_pass);
	composer.addPass(output_pass);

	return {
		controls: controls,
		container: container,
		scene: scene,
		camera: camera,
		// composer: final_composer,
		composer: composer,
		renderer: renderer,
		animations: [],

		rotation_center: new THREE.Vector3()
	}
}

/**
 *
 * @param {Object} scene    Threejs objects returned by init function.
 * @param {Object} data     Data for elements, connections.
 */
function fillScene(scene, data, points) {
	var x = 0,
		y = 0,
		z = 0,
		processed = {};

	data.elements.forEach(elm => {

		var distance,
			children = data.connections.filter(connection => {
				return connection.parent === elm.id;
			});

		if (elm.id in processed) {
			if (!children.length) {
				return;
			}

			distance = 3;
			x = processed[elm.id].pos[0];
			y = processed[elm.id].pos[1];
			z = processed[elm.id].pos[2];
			__log(`old parent ${elm.id}`, processed[elm.id]);
		}
		else {
			var mesh = new THREE.Mesh(geometry[elm.geometry], material.blue.clone());
			distance = 8;
			mesh.mouseenter = mouseenter;
			mesh.mouseleave = mouseleave;
			mesh.mouseclick = mouseclick;
			mesh.position.set(x,y,z);
			//mesh.add(scene.sprite_glow.clone());
			scene.scene.add(mesh);

			var text = new TextLabelNode();
			text.setHTML(elm.deails);
			text.parent = mesh;
			scene.labels.push(text);
			scene.container.append(text.element);

			// how element should be positioned when have more than one parent?!
			processed[elm.id] = {
				pos: [x, y, z],
				meshid: mesh.id
			};
			__log(`new parent ${elm.id}`, processed[elm.id]);
		}

		__log(`found ${children.length} children`, children);

		if (!children.length) {
			return;
		}

		distance *= children.length;

		points(children.length, distance*3).forEach((pos, i) => {
			var elm = data.elements.filter(elm => {
				return elm.id === children[i].child;
			}).pop();
			var mesh = new THREE.Mesh(geometry[elm.geometry], material.blue.clone());
			mesh.mouseenter = mouseenter;
			mesh.mouseleave = mouseleave;
			mesh.mouseclick = mouseclick;
			mesh.position.set(x + pos[0], y + pos[1], z + pos[2]);
			// mesh.layers.enable(BLOOM_SCENE);
			// mesh.add(scene.sprite_glow.clone());
			scene.scene.add(mesh);

			var line_geometry = new THREE.Geometry();
			line_geometry.vertices.push(
				new THREE.Vector3(x, y, z),
				new THREE.Vector3(x + pos[0], y + pos[1], z + pos[2])
			);
			var line = new THREE.Line(line_geometry, new THREE.LineBasicMaterial({color: 0x0a466a, transparent: true,
				opacity: 0.3
			}));
			line.mouseenter = mouseenter;
			line.mouseleave = mouseleave;
			// line.layers.enable(ENTIRE_SCENE);
			scene.scene.add(line);

			// Add data-flow-animation
			// var flow_mesh = scene.sprite_glow.clone();
			var flow_mesh = new THREE.Mesh(geometry.connection, material.connection.clone());
			flow_mesh.layers.enable(BLUR_SCENE);

			// https://github.com/mrdoob/three.js/blob/2136a132055c579bb140f5198992c7eb21256e83/examples/jsm/animation/AnimationClipCreator.js#L69
			// var duration = 3, pulseScale = 3;
			// var times = [], values = [], tmp = new THREE.Vector3();

			// for ( var i = 0; i < duration * 50; i ++ ) {

			// 	times.push( i / 50 );

			// 	//var scaleFactor = Math.random() * pulseScale;
			// 	var scaleFactor = (i%12)*pulseScale/2;
			// 	tmp.set(scaleFactor, scaleFactor, scaleFactor)
			// 		.toArray(values, values.length);

			// }
			// for ( var i = 0; i < duration * 20; i ++ ) {

			// 	times.push( i / 20 );

			// 	//var scaleFactor = Math.random() * pulseScale;
			// 	var scaleFactor = (i)%10 * pulseScale/5;
			// 	tmp.set( scaleFactor, scaleFactor, scaleFactor ).
			// 		toArray( values, values.length );

			// }
			// var pulse = new THREE.VectorKeyframeTrack( '.scale', times, values );

			var direction = Math.random() >= 0.5
				? [ x + pos[0], y + pos[1], z + pos[2], x, y, z ]
				: [ x, y, z, x + pos[0], y + pos[1], z + pos[2] ];
			var positions = new THREE.VectorKeyframeTrack( '.position', [ 0, 1 ], direction, THREE.LoopRepeat );
			var clip = new THREE.AnimationClip('Action', 3, [positions]);
			// var clip = new THREE.AnimationClip( 'Action', 1, [ positions, pulse ] );
			var mixer = new THREE.AnimationMixer( flow_mesh );
			var clipAction = mixer.clipAction( clip );
			clipAction.play();
			scene.animations.push(mixer);
			scene.scene.add(flow_mesh);


			var text = new TextLabelNode();
			text.setHTML(elm.details);
			text.parent = mesh;
			scene.labels.push(text);
			scene.container.append(text.element);

			processed[elm.id] = {
				pos: pos,
				meshid: mesh.id
			};
			__log(`	adding ${i} child ${elm.id}`, children[i], processed[elm.id]);
		});
	});
}

function updateLabelsPositions(scene) {
	var width = scene.container.width(),
		height = scene.container.height();

	scene.labels.each(label => {
		label.updatePosition(scene.controls.object, width, height);
	});
}

// var a = 0;
// var x = 0, y = 0, z = 1000;
// var ca = 90;

/**
 * Animation frame handler for all 3d canvas objects.
 */
function animate() {
	var delta = clock.getDelta();
	requestAnimationFrame(animate);

	Object.values(widgets_canvas).each(scene => {
		updateRotationCenter(scene);
		// scene.camera.position.set(x, y, z+Math.sin(a)*600);
		// a = a + delta;
		// scene.camera.rotation.y = ca + Math.sin(a);
		scene.controls.update();
		// scene.renderer.render(scene.scene, scene.camera);
		scene.camera.updateMatrixWorld();
		updateLabelsPositions(scene);
		sceneMouseOverHandler(scene);

		if (scene.animations) {
			scene.animations.each(mixer => {
				mixer.update(delta);
			})
		}

		scene.composer.render(delta);
	});
};

/**
 * Find 3d objects hovered by mouse.
 *
 * @param {Object} scene     Scene object
 */
function sceneMouseOverHandler(scene) {
	raycaster.setFromCamera(scene.mouse, scene.camera);
	var objects = raycaster.intersectObjects(scene.scene.children),
		uuids = $.map(objects, o => { return o.object.uuid; });

	if (objects.length && scene.intersected && uuids.indexOf(scene.intersected.object.uuid) != -1) {
		return;
	}

	if (!objects.length) {
		if (scene.intersected && 'mouseleave' in scene.intersected.object) {
			scene.intersected.object.mouseleave(scene);
		}

		scene.intersected = null;
		return;
	}

	if (scene.intersected && 'mouseleave' in scene.intersected.object) {
		scene.intersected.object.mouseleave(scene);
		scene.intersected = null;
	}

	if ('mouseenter' in objects[0].object) {
		scene.intersected = objects[0];
		scene.intersected.object.mouseenter(scene);
	}
}

function mouseenter(scene) {
	if (scene.mouse.clock + 1000 > (new Date).getTime()) {
		scene.controls.autoRotate = false;
	}
}

function mouseleave(scene) {
	scene.controls.autoRotate = true;
}

function mouseclick(scene) {
	scene.rotation_center = scene.intersected.point;
	// var vect3 = scene.intersected.point;

	// __log('click', scene.controls.target, vect3);
	// scene.controls.target.set(vect3.x, vect3.y, vect3.z);
}

function updateRotationCenter(scene) {
	var vect3 = scene.rotation_center,
		camera = scene.controls.target;

	['x', 'y', 'z'].forEach(axis => {
		if (Math.abs(vect3[axis] - camera[axis]) < 0.5) {
			camera[axis] = vect3[axis];
		}
		else {
			camera[axis] -= vect3[axis] > camera[axis]
				? (camera[axis] - vect3[axis])/2
				: (camera[axis] - vect3[axis])/2;
		}
	});
}

/**
 * Generate ${count} coordinates distributing over sphere.
 *
 * @param {number} count    Desired points count to generate.
 *
 * @return {Array}
 */
function pointsDistributionSphere(count, distance) {
	// source: https://stackoverflow.com/a/26127012
	var points = [],
		offset = 2/count,
		increment = Math.PI * (3 - Math.sqrt(5)),
		phi, x, y, z, r;

	for (i = 0; i < count; i++) {
		phi = (i % count) * increment;
		y = ((i * offset) - 1) + (offset / 2);
		r = Math.sqrt(1 - Math.pow(y, 2));
		x = Math.cos(phi) * r;
		z = Math.sin(phi) * r;

		points.push([x * distance, y * distance, z * distance]);
	}

	return points;
}

function TextLabelNode() {
	this.parent = false;
	this.position = new THREE.Vector3(0,0,0);
	this.element = $('<div class="label-3dcanvas"/>')[0];
	return this;
}

TextLabelNode.prototype.setHTML = function (html) {
	this.element.innerHTML = html||'';
};

TextLabelNode.prototype.updatePosition = function(camera, width, height) {
	if (this.parent) {
		this.position.copy(this.parent.position);
	}

	var vector = this.position.project(camera);
	vector.x = (vector.x + 1)/2 * width;
	vector.y = -(vector.y - 1)/2 * height;

	this.element.style.left = vector.x + 'px';
	this.element.style.top = vector.y + 'px';

	// this.setHTML(distance.toFixed(2));
	//this.setHTML(camera.position.distanceTo(this.position).toFixed(2));
	//this.setHTML(camera.position.distanceTo(vector).toFixed(2));
	this.element.style.display = this.position.z < 0 ? 'none' : 'block';

	this.element.style.opacity = 1.5-Math.abs(camera.position.distanceTo(this.position)/1500);
};

/**
 * Return object with shaders GLSL code.
 */
function getShadersGLSL() {
	return {
		bloomVertexShader: `
		varying vec2 vUv;

		void main() {
			vUv = uv;
			gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
		}
		`,
		bloomFragmentShader: `
		uniform sampler2D baseTexture;
		uniform sampler2D bloomTexture;
		varying vec2 vUv;

		vec4 getTexture(sampler2D texelToLinearTexture) {
			return mapTexelToLinear(texture2D(texelToLinearTexture , vUv));
		}

		void main() {
			gl_FragColor = (getTexture(baseTexture) + vec4(1.0) * getTexture(bloomTexture));
		}
		`
	}
}

})(jQuery);
