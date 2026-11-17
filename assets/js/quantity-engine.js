/**
 * Auger Rénovation - Quantity Calculation Engine
 * Computes exact purchase units required based on room dimensions and material metadata.
 */

function calculateMaterialQuantity(material, dimensions) {
  // dimensions = { floorArea: sqft, wallArea: sqft, perimeter: linear_ft, ceilingHeight: ft }
  const qtyType = material.qty_type || 'fixed';
  const coverage = parseFloat(material.coverage_per_unit) || 1.0;
  const wasteFactor = parseFloat(material.waste_factor) || 1.10; // Default 10% waste

  let baseQuantity = 0;

  switch (qtyType) {
    case 'floor_area': // e.g., Tiles, Flooring, Underlayment
      const floorSqFt = dimensions.floorArea || 0;
      baseQuantity = (floorSqFt / coverage) * wasteFactor;
      break;

    case 'net_wall_area': // e.g., Drywall, Cement Board, Vapor Barrier
      const wallSqFt = dimensions.wallArea || 0;
      baseQuantity = (wallSqFt / coverage) * wasteFactor;
      break;

    case 'perimeter': // e.g., Baseboards (Plinthes), Molding
      const perimeterFt = dimensions.perimeter || 0;
      // coverage here represents length per piece/box (e.g., 12ft per piece)
      baseQuantity = (perimeterFt / coverage) * wasteFactor;
      break;

    case 'paint_area': // e.g., Paint gallons (coverage in sqft per gallon)
      const paintSqFt = dimensions.wallArea || 0;
      baseQuantity = (paintSqFt / coverage); // Usually accounts for coats in coverage rating
      break;

    case 'fixed':
    default:
      baseQuantity = material.default_quantity || 1;
      break;
  }

  // Always round up to the next whole purchase unit (you can't buy 0.4 boxes of tile)
  return Math.ceil(baseQuantity);
}