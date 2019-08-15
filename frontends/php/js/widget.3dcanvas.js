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
		sphere: new THREE.SphereGeometry(1, 70, 70, 0, Math.PI * 2, 0, Math.PI * 2),
		icosahedron: new THREE.IcosahedronGeometry(1, 5)
	},
	material = {
		red: new THREE.MeshStandardMaterial({color: 0xee0808, flatShading: true}),
		green: new THREE.MeshStandardMaterial({color: 0x08ee08, flatShading: true}),
		blue: new THREE.MeshStandardMaterial({color: 0x0a466a, flatShading: true}),
		white: new THREE.MeshStandardMaterial({color: 0xffffff, flatShading: true}),
		inner_sphere: new THREE.MeshStandardMaterial({color: 0x2020ff, transparent: true, opacity: 0.1})
	},
	shaders = getShadersGLSL(),
	INTERSECTED;// TODO: move to widgets_canvas object

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
	var camera = new THREE.PerspectiveCamera(70, container.width() / container.height(), 0.1, 1000);
	var renderer = new THREE.WebGLRenderer({ alpha: true });
	var controls = new THREE.OrbitControls(camera, renderer.domElement);
	var scene = new THREE.Scene();

	var light_hemisphere = new THREE.HemisphereLight(0xffffff, 0x444444);
	light_hemisphere.position.set(0, 1000, 0);
	scene.add(light_hemisphere);
	var light_directional = new THREE.DirectionalLight(0xffffff, 0.8);
	light_directional.position.set(-3000, 1000, -1000);
	scene.add(light_directional);


	camera.position.set(15, 20, 100);
	controls.update();

	renderer.setSize(container.width(), container.height() - 10);
	container.append(renderer.domElement);

	var sprite_glow_material = new THREE.SpriteMaterial({
		map: new THREE.ImageUtils.loadTexture('assets/img/glow.png' ),
		useScreenCoordinates: false, alignment: new THREE.Vector2(0, 0),
		color: 0x0a466a, transparent: true, blending: THREE.AdditiveBlending
	});
	var sprite_glow = new THREE.Sprite(sprite_glow_material);
	sprite_glow.scale.set(7, 7, 1.0);

	// Enable camera auto rotation
	controls.target = new THREE.Vector3(3, 7, 0);
	controls.update();
	controls.autoRotate = true;
	controls.autoRotateSpeed = 3.0;

	return {
		controls: controls,
		container: container,
		scene: scene,
		camera: camera,
		renderer: renderer,
		sprite_glow: sprite_glow,
		animations: []
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
			mesh.add(scene.sprite_glow.clone());
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
			mesh.add(scene.sprite_glow.clone());
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
			scene.scene.add(line);

			// Add data-flow-animation
			var flow_mesh = scene.sprite_glow.clone();

			// https://github.com/mrdoob/three.js/blob/2136a132055c579bb140f5198992c7eb21256e83/examples/jsm/animation/AnimationClipCreator.js#L69
			var duration = 1, pulseScale = 20;
			var times = [], values = [], tmp = new THREE.Vector3();

			for ( var i = 0; i < duration * 10; i ++ ) {

				times.push( i / 10 );

				//var scaleFactor = Math.random() * pulseScale;
				var scaleFactor = ((i%4) + 1)/10 * pulseScale;
				tmp.set( scaleFactor, scaleFactor, scaleFactor ).
					toArray( values, values.length );

			}
			// for ( var i = 0; i < duration * 20; i ++ ) {

			// 	times.push( i / 20 );

			// 	//var scaleFactor = Math.random() * pulseScale;
			// 	var scaleFactor = (i)%10 * pulseScale/5;
			// 	tmp.set( scaleFactor, scaleFactor, scaleFactor ).
			// 		toArray( values, values.length );

			// }
			var pulse = new THREE.VectorKeyframeTrack( '.scale', times, values );

			var direction = Math.random() >= 0.5
				? [ x + pos[0], y + pos[1], z + pos[2], x, y, z ]
				: [ x, y, z, x + pos[0], y + pos[1], z + pos[2] ];
			var positions = new THREE.VectorKeyframeTrack( '.position', [ 0, 1 ], direction, THREE.LoopPingPong );
			var clip = new THREE.AnimationClip( 'Action', 1, [ positions, pulse ] );
			var mixer = new THREE.AnimationMixer( flow_mesh );
			var clipAction = mixer.clipAction( clip );
			clipAction.play();
			scene.animations.push(mixer);
			scene.scene.add(flow_mesh);


			var text = new TextLabelNode();
			text.setHTML(elm.deails);
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
		label.updatePosition(scene.camera, width, height);
	});
}

/**
 * Animation frame handler for all 3d canvas objects.
 */
function animate() {
	var delta = clock.getDelta();
	requestAnimationFrame(animate);

	Object.values(widgets_canvas).each(scene => {
		scene.controls.update();
		scene.renderer.render(scene.scene, scene.camera);
		scene.camera.updateMatrixWorld();
		updateLabelsPositions(scene);
		sceneMouseOverHandler(scene);

		if (scene.animations) {
			scene.animations.each(mixer => {
				mixer.update(delta);
			})
		}
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
	__log('lookat', scene.intersected);
	// scene.camera.lookAt(scene.intersected);
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
	if(parent) {
		this.position.copy(this.parent.position);
	}

	var vector = this.position.project(camera);
	vector.x = (vector.x + 1)/2 * width;
	vector.y = -(vector.y - 1)/2 * height;

	this.element.style.left = vector.x + 'px';
	this.element.style.top = vector.y + 'px';
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
