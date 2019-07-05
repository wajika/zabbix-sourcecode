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
	geometry = {
		sphere: new THREE.SphereGeometry(1, 70, 70, 0, Math.PI * 2, 0, Math.PI * 2),
		icosahedron: new THREE.IcosahedronGeometry(1, 5)
	},
	material = {
		red: new THREE.MeshStandardMaterial({color: 0xee0808, flatShading: true}),
		green: new THREE.MeshStandardMaterial({color: 0x08ee08, flatShading: true}),
		blue: new THREE.MeshStandardMaterial({color: 0x0000ff, flatShading: true}),
		inner_sphere: new THREE.MeshStandardMaterial({color: 0x2020ff, transparent: true, opacity: 0.1})
	},
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
	widgets_canvas[widgetid].mouse = {x: 0, y:0};
	fillScene(widgets_canvas[widgetid], ev.scene, pointsDistributionSphere);

	container.on('mousemove', {widgetid: widgetid}, containerMouseMoveHandler);
}

/**
 * Updates widget mouse position to scene.
 *
 * @param {Object} ev         jQuery event object.
 */
function containerMouseMoveHandler(ev) {
	if (!ev.data || !ev.data.widgetid) {
		return;
	}

	ev.preventDefault();

	var widgetid = ev.data.widgetid,
		container = $(ev.target),
		rect = ev.target.getBoundingClientRect();

	widgets_canvas[widgetid].mouse = {
		x: ((ev.clientX - rect.left) / container.width()) * 2 - 1,
		y: - ((ev.clientY - rect.top) / container.height()) * 2 + 1
	}
  }

/**
 * Initialize three.js scene.
 *
 * @param {Object} container   Dom element, 3d canvas container.
 */
function init(container) {
	var camera = new THREE.PerspectiveCamera(70, container.width() / container.height(), 0.1, 1000);
	// var camera = new THREE.OrthographicCamera(container.width() / -2, container.width() / 2, container.height() / 2,
	// 	container.height() / -2, 1, 1000
	// );
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
		color: 0x0000ff, transparent: true, blending: THREE.AdditiveBlending
	});
	var sprite_glow = new THREE.Sprite(sprite_glow_material);
	sprite_glow.scale.set(7, 7, 1.0);


	// Camera auto rotation
	controls.target = new THREE.Vector3(3, 7, 0);
    controls.update();
    controls.autoRotate = true;
    controls.autoRotateSpeed = 3.0;

	return {
		controls: controls,
		scene: scene,
		camera: camera,
		renderer: renderer,
		sprite_glow: sprite_glow
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
			mesh.position.set(x,y,z);
			mesh.add(scene.sprite_glow.clone());
			scene.scene.add(mesh);

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

		// DEBUG: inner sphere where all children will be distributed by pointsDistributionSphere.
		var inner_sphere = new THREE.Mesh(
			new THREE.SphereGeometry(distance, 70, 70, 0, Math.PI * 2, 0, Math.PI * 2),
			material.inner_sphere
		);
		inner_sphere.position.set(x,y,z);
		//scene.scene.add(inner_sphere);

		points(children.length, distance).forEach((pos, i) => {
			var elm = data.elements.filter(elm => {
				return elm.id === children[i].child;
			}).pop();
			var mesh = new THREE.Mesh(geometry[elm.geometry], material.blue.clone());
			mesh.position.set(x + pos[0], y + pos[1], z + pos[2]);
			mesh.add(scene.sprite_glow.clone());
			scene.scene.add(mesh);

			var line_geometry = new THREE.Geometry();
			line_geometry.vertices.push(
				new THREE.Vector3(x, y, z),
				new THREE.Vector3(x + pos[0], y + pos[1], z + pos[2])
			);
			var line = new THREE.Line(line_geometry, new THREE.LineBasicMaterial({color: 0x0000ff, transparent: true,
				opacity: 0.3
			}));
			scene.scene.add(line);

			processed[elm.id] = {
				pos: pos,
				meshid: mesh.id
			};
			__log(`	adding ${i} child ${elm.id}`, children[i], processed[elm.id]);
		});
	});
}

/**
 * Animation frame handler for all 3d canvas objects.
 */
function animate() {
	requestAnimationFrame(animate);

	Object.values(widgets_canvas).each(scene => {
		scene.controls.update();
		scene.renderer.render(scene.scene, scene.camera);
		scene.camera.updateMatrixWorld();

		raycaster.setFromCamera(scene.mouse, scene.camera);
		markMouseIntersection(scene);
	});
};

/**
 * Find 3d objects hovered by mouse.
 *
 * @param {Object} scene     Scene object
 */
function markMouseIntersection(scene)
{
	var tick = Date.now() * 0.0002;
	var intersects = raycaster.intersectObjects(scene.scene.children);

	if (intersects.length > 0) {
		scene.controls.autoRotate = false;

		__log('intersected', intersects);
		if ( INTERSECTED != intersects[ 0 ].object ) {
			if ( INTERSECTED ) INTERSECTED.material.color.setHex(INTERSECTED.currentHex);
			INTERSECTED = intersects[ 0 ].object;
			INTERSECTED.currentHex = INTERSECTED.material.color.getHex();
			
			if (INTERSECTED.children) {
				INTERSECTED.material.color.setHex(0xffffff);
			}
			else {
				INTERSECTED.material.color.setHex(0xffffff);
			}
		}
	} else {
		if ( INTERSECTED ) INTERSECTED.material.color.setHex(INTERSECTED.currentHex);
		INTERSECTED = null;

		scene.controls.autoRotate = true;
	}
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
})(jQuery);
