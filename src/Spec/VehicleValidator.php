<?php

declare(strict_types=1);

namespace App\Spec;

/** Checks vehicle capacity and load-bay geometry. */
final class VehicleValidator
{
    /**
     * The `vehicle:` block: whether it belongs on this spec at all, and whether the two boxes and the two masses
     * make sense together.
     *
     * **The payload check is the one with consequences outside this repository.** Every other rule here protects a
     * render. A permitted gross mass at or below the mass in service means the vehicle may legally carry nothing,
     * which is never true of a real van and is always a transcribed digit — and a packer reading it would either
     * refuse every load or, if the sign went the other way, cheerfully authorise an overloaded one. So it is an
     * error rather than a warning, and it names both fields of the Zulassungsbescheinigung so the reader knows which
     * document to go back to.
     *
     * **The bay is checked against the outer box** for the same reason a truss chord is: `geometry.dimensions_m` is
     * what the rest of the repository measures this device by, and an inside larger than the outside is a unit slip
     * or a copied row from the wrong body variant. That last one is not hypothetical — the front-wheel-drive H3 bay
     * is 2144 mm against the rear-wheel-drive 2048 mm, and the L4 body only comes rear-wheel drive.
     *
     * @return list<string>
     */
    public function validate(DeviceSpec $spec): array
    {
        $vehicle = $spec->vehicle;
        $isVehicle = Category::Vehicle === $spec->category;

        if (null === $vehicle) {
            return $isVehicle
                ? ["category '{$spec->category->value}' needs a `vehicle` block stating at least permitted_gross_kg"]
                : [];
        }
        if (!$isVehicle) {
            return ["a `vehicle` block belongs to category 'vehicle', not '{$spec->category->value}'"];
        }

        $messages = [];

        // **A vehicle is drawn as a cage, and a bay is still optional.** Requiring both would have made them imply
        // each other and quietly killed the reason the bay is optional at all: a van can be specified from its
        // papers before anybody has been inside it, and no registration document states a load bay. So a bayless
        // vehicle draws its outline alone — which is the honest picture of a van whose inside nobody has measured,
        // and is still a cage rather than a solid.
        if (Shape::LoadBay !== $spec->shape) {
            $messages[] = sprintf(
                'a vehicle is drawn as a cage, so geometry.shape should be `load-bay` rather than `%s` — a solid'
                .' van is the largest object in any picture that includes it and hides the rig it carries',
                $spec->shape->value,
            );
        }

        if ($vehicle->permittedGrossKg <= $spec->weightKg) {
            $messages[] = sprintf(
                'vehicle.permitted_gross_kg (%s, Zulassungsbescheinigung F.2) is not above physical.weight_kg'
                .' (%s, field G), so the payload works out at %s kg. One of the two is transcribed wrong',
                $vehicle->permittedGrossKg,
                $spec->weightKg,
                round($vehicle->payloadKg($spec->weightKg), 1),
            );
        }

        $bay = $vehicle->loadBay;
        if (null === $bay) {
            return $messages;
        }

        foreach ([
            'width' => [$bay->width, $spec->dimensions->width],
            'height' => [$bay->height, $spec->dimensions->height],
            'depth' => [$bay->depth, $spec->dimensions->depth],
        ] as $axis => [$inside, $outside]) {
            if ($inside <= 0.0) {
                $messages[] = "vehicle.load_bay_m.{$axis} must be greater than 0, got {$inside}";
            } elseif ($inside > $outside) {
                $messages[] = sprintf(
                    'vehicle.load_bay_m.%s (%s) is bigger than the vehicle — geometry.dimensions_m.%s is %s.'
                    .' The bay is the inside and the dimensions are the outside',
                    $axis,
                    $inside,
                    $axis,
                    $outside,
                );
            }
        }

        if (null !== $vehicle->widthBetweenArchesM && $vehicle->widthBetweenArchesM > $bay->width) {
            $messages[] = sprintf(
                'vehicle.load_bay_m.width_between_arches (%s) is wider than the bay itself (%s) — the arches are'
                .' what narrow it',
                $vehicle->widthBetweenArchesM,
                $bay->width,
            );
        }

        foreach ([
            'door_aperture_width' => [$vehicle->doorApertureWidthM, $bay->width],
            'door_aperture_height' => [$vehicle->doorApertureHeightM, $bay->height],
        ] as $key => [$aperture, $limit]) {
            if (null !== $aperture && $aperture > $limit) {
                $messages[] = sprintf(
                    'vehicle.load_bay_m.%s (%s) is bigger than the bay behind it (%s) — a doorway cannot open onto'
                    .' more than there is',
                    $key,
                    $aperture,
                    $limit,
                );
            }
        }

        return $messages;
    }
}
