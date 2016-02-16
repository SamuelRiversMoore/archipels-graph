var width = 1400,
    height = 800;

var color = d3.scale.category20();

var force = d3.layout.force()
    .charge(-30)
    .linkDistance(function(link, index) { 
			if(link.type == 'table-table') {
				return ( Math.min( 1, 50 - link.proximity ) * 300 );
			} else {
				return 1;
			}
		})
    //.linkStrength(function(link, index) { return link.strength })
    .size([width, height]);

var svg = d3.select("body").append("svg")
    .attr("width", width)
    .attr("height", height);

d3.json("atlas-total.json", function(error, graph) {
  if (error) throw error;

  force
      .nodes(graph.nodes)
      .links(graph.links)
      .start();

  var link = svg.selectAll(".link")
      .data(graph.links)
    	.enter().append("line")
      .attr("class", "link")
      .style("stroke-width", function(d) { 
				if(d.type == 'table-table') {
					return ( d.proximity * .5 );
				} else {
				 	return 1;
			 	}
			} );

  var node = svg.selectAll(".node")
      .data(graph.nodes)
    	.enter().append("circle")
      .attr("class", "node")
      .attr("r", 5)
      .style("fill", function(d) { return color(d.type); })
      .call(force.drag);

  node.append("title")
      .text(function(d) { return d.name; });

  force.on("tick", function() {
    link.attr("x1", function(d) { return d.source.x; })
        .attr("y1", function(d) { return d.source.y; })
        .attr("x2", function(d) { return d.target.x; })
        .attr("y2", function(d) { return d.target.y; });

    node.attr("cx", function(d) { return d.x; })
        .attr("cy", function(d) { return d.y; });
  });
});