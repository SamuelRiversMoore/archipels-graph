function redraw() {
			vis.attr("transform",
							 "translate(" + d3.event.translate + ")"
							 + " scale(" + d3.event.scale + ")");
	}
	function updateWindow(){
			w = $('#chart').width();
			h = $('#chart').height();
			svg.attr("width", w).attr("height", w);
	}
	window.onresize = updateWindow;

	
	var draw = function(json) {
		var force = d3.layout.force()
				.charge(-120)
				.linkDistance(30)
				.nodes(json.nodes)
				.links(json.links)
				.size([w, h]);

/*		var voronoi = d3.geom.voronoi()
			.x(function(d) {return x(d.x); })
			.y(function(d) {return y(d.y); })
			.clipExtent([[0,0],[w,h]]);

		//Create the Voronoi grid
		svg.selectAll(".polygon")
			.data(voronoi(force))
			.enter().append("path")
			.attr('class','polygon')
			.attr("d",function(d){return "M" + d.join("L") + "Z";})
			.datum(function(d, i) { return d.point; })
			.attr("class", function(d,i) { return "voronoi " + d.id; })
			.style("stroke", "#000")
			.style("fill", "#2074A0")
			.style("opacity", ".3")
			.style("pointer-events", "all")
			.on("mouseover", function(d){document.getElementById('legend').innerHTML = d.id})
			.on("mouseout", function(d){document.getElementById('legend').innerHTML = ''});

*/

		force.on('end', function() {
			node.attr('r', 5)
				.attr('cx', function(d) { return d.x; })
				.attr('cy', function(d) { return d.y; });

			link.attr('x1', function(d) { return d.source.x; })
				.attr('y1', function(d) { return d.source.y; })
				.attr('x2', function(d) { return d.target.x; })
				.attr('y2', function(d) { return d.target.y; });
		});
		force.start();

		var link = vis.selectAll("line.link")
				.data(json.links)
			.enter().append("svg:line")
				.attr("class", "link")
				.style("stroke-width", function(d) { return Math.sqrt(d.value); })
				.attr("x1", function(d) { return d.source.x; })
				.attr("y1", function(d) { return d.source.y; })
				.attr("x2", function(d) { return d.target.x; })
				.attr("y2", function(d) { return d.target.y; });

		var node = vis.selectAll("circle.node")
				.data(json.nodes)
			.enter().append("svg:circle")
				.attr("class", "node")
				.attr("cx", function(d) { return d.x; })
				.attr("cy", function(d) { return d.y; })
				.attr("r", 5)
				.style("fill", function(d) { return fill(d.group); })
				.call(force.drag);

		node.append("svg:title")
				.text(function(d) { return d.name; });

		vis.style("opacity", 1e-6)
			.transition()
				.duration(1000)
				.style("opacity", 1);

		force.on("tick", function() {
			link.attr("x1", function(d) { return d.source.x; })
					.attr("y1", function(d) { return d.source.y; })
					.attr("x2", function(d) { return d.target.x; })
					.attr("y2", function(d) { return d.target.y; });

			node.attr("cx", function(d) { return d.x; })
					.attr("cy", function(d) { return d.y; });
		});
	};




	$.getJSON("atlas.json", function(json) {
		var data = json;
		console.log(data);
		draw(data);
	});

})();
