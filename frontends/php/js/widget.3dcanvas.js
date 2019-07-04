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
	geometry = {
		sphere: new THREE.SphereGeometry(1, 70, 70, 0, Math.PI * 2, 0, Math.PI * 2)
	},
	material = {
		red: new THREE.MeshStandardMaterial({color: 0xee0808, flatShading: true}),
		green: new THREE.MeshStandardMaterial({color: 0x08ee08, flatShading: true})
	};

$.subscribe('init.widget.3dcanvas', initWidget3dCanvasHandler);
animate();

function initWidget3dCanvasHandler(ev) {
	__log('init.widget.3dcanvas', arguments);
	// find by unique id passed in ev.widgetid field
	var container = $('[data-3d-canvas]').first();
	var widgetid = '1234';

	widgets_canvas[widgetid] = init(container);
	fillScene(widgets_canvas[widgetid], ev.scene, pointsDistributionSphere);
}

/**
 * Initialize three.js scene.
 *
 * @param {Object} container   Dom element, 3d canvas container.
 */
function init(container) {
	var scene = new THREE.Scene();
	var camera = new THREE.PerspectiveCamera(50, container.width() / container.height(), 0.1, 1000);
	var renderer = new THREE.WebGLRenderer({ alpha: true });
	var controls = new THREE.OrbitControls(camera, renderer.domElement);

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

	return {
		scene: scene,
		camera: camera,
		renderer: renderer
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

		if (elm.id in processed)
		{
			var mesh = new THREE.Mesh(geometry.sphere, material.green);
			var distance = 3;

			x = processed[elm.id].pos[0];
			y = processed[elm.id].pos[1];
			z = processed[elm.id].pos[2];

			mesh.position.set(x,y,z);
			scene.scene.add(mesh);
			__log(`old parent ${elm.id}`, processed[elm.id]);
		}
		else
		{
			var mesh = new THREE.Mesh(geometry.sphere, material.red);
			var distance = 1;

			mesh.position.set(x,y,z);
			scene.scene.add(mesh);

			// how element should be positioned when have more than one parent?!
			processed[elm.id] = {
				pos: [x, y, z],
				meshid: mesh.id
			};
			__log(`new parent ${elm.id}`, processed[elm.id]);
		}

		var children = data.connections.filter(connection => {
			return connection.parent === elm.id;
		});

		__log(`found ${children.length} children`, children);

		if (!children.length)
		{
			return;
		}

		distance *= children.length;

		points(children.length, distance).forEach((pos, i) => {
			var mesh = new THREE.Mesh(geometry.sphere, material.green);

			mesh.position.set(x + pos[0], y + pos[1], z + pos[2]);
			scene.scene.add(mesh);

			processed[children[i].child] = {
				pos: pos,
				meshid: mesh.id
			};
			__log(`	adding ${i} child ${children[i].child}`, children[i], processed[children[i].child]);
		});
	});
}

/**
 * Animation frame handler for all 3d canvas objects.
 */
function animate() {
	requestAnimationFrame(animate);

	Object.values(widgets_canvas).each(scene => {
		scene.renderer.render(scene.scene, scene.camera);
	});
};

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

	for (i = 0; i < count; i++)
	{
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
