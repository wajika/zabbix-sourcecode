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

var widgets = {},
	raycaster = new THREE.Raycaster(),
	clock = new THREE.Clock(),
	delta;

$.subscribe('init.widget.3dcanvas', function(e) {
	// find by unique id passed in ev.widgetid field
	var $container = $('[data-3d-canvas]').first(),
		gltf = 'assets/gltf/scene.gltf',
		id = '1234';

	widgets[id] = new THREEWidget($container, id, gltf);
});

function render() {
	delta = clock.getDelta();

	Object.values(widgets).each(function(widget, id) {
		widget.render();
		widget.mouse.over && widget.interact();
	});

	requestAnimationFrame(render);
};

render();

function THREEWidget($container, id, gltf_uri) {
	var width = $container.width(),
		height = $container.height(),
		scene;

	this.id = id;
	this.mouse = {
		over: false,
		activity: 0
	};
	this.intersected = [];
	this.height = height;
	this.width = width;

	// camera
	this.camera = new THREE.PerspectiveCamera(60, width / height, 0.1, 1000);
	this.camera.position.set(0, 0, 20);
	this.camera.lookAt(0, 0, 0);

	// renderer
	this.renderer = new THREE.WebGLRenderer({ alpha: true });
	this.renderer.shadowMap.enabled = true;
	this.renderer.setPixelRatio(window.devicePixelRatio);
	this.renderer.setSize(width, height - 10);

	// orbit control
	this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
	this.controls.target = new THREE.Vector3(3, 7, 0);
	this.controls.autoRotate = true;
	this.controls.autoRotateSpeed = 3.0;

	this.scene = new THREE.Scene();

	// lights
	var light_hemisphere = new THREE.HemisphereLight(0xffffff, 0x444444);
	light_hemisphere.position.set(0, 1000, 0);
	this.scene.add(light_hemisphere);
	var light_directional = new THREE.DirectionalLight(0xffffff, 0.8);
	light_directional.position.set(-3000, 1000, -1000);
	this.scene.add(light_directional);

	$container.append(this.renderer.domElement);
	this.controls.update();

	// glTF
	scene = this.scene;
	this.loader = new THREE.GLTFLoader();
	this.loader.load(gltf_uri, function(data) {
		scene.add(data.scene);

		// console. log(`gltf loaded: ${dumpObject(data.scene).join('\n')}`);
	});

	$container
		.on('mousemove', this.handlers.mousemove.bind(this))
		.on('mouseout', this.handlers.mousemove.bind(this))
		.on('click', this.handlers.click.bind(this));
}

THREEWidget.prototype.render = function() {
	this.controls.autoRotate = this.mouse.activity + 2 < clock.getElapsedTime();
	this.controls.update();
	this.renderer.render(this.scene, this.camera);
}

THREEWidget.prototype.interact = function() {
	raycaster.setFromCamera(this.mouse, this.camera);

	this.intersected = raycaster.intersectObjects([this.scene], true);

	// $.each(raycaster.intersectObjects(this.scene.children), function(_, o) {
	// 	if (o.object && 'mouseclick' in o.object) {
	// 		scene.intersected = o;
	// 		o.object.mouseclick(scene);

	// 		return false;
	// 	}
	// });
}

THREEWidget.prototype.handlers = {
	mousemove: function(e) {
		var rect = e.target.getBoundingClientRect();

		this.mouse = {
			x: ((e.clientX - rect.left) / this.width) * 2 - 1,
			y: - ((e.clientY - rect.top) / this.height) * 2 + 1,
			over: e.type === 'mousemove',
			activity: clock.getElapsedTime()
		}
	},
	click: function() {
		console. log('click', this.intersected);
	}
};

function dumpObject(obj, lines = [], isLast = true, prefix = '') {
	const localPrefix = isLast ? '└─' : '├─';
	lines.push(`${prefix}${prefix ? localPrefix : ''}${obj.name || '*no-name*'} [${obj.type}]`);
	const newPrefix = prefix + (isLast ? '  ' : '│ ');
	const lastNdx = obj.children.length - 1;
	obj.children.forEach((child, ndx) => {
		const isLast = ndx === lastNdx;
		dumpObject(child, lines, isLast, newPrefix);
	});
	return lines;
}

})(jQuery);
