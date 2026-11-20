# Room scanning with one POCO F7

The proposed pilot uses one POCO F7 and two 2 m folding rulers to reconstruct a static room.
The target is a maximum absolute error of 30 mm on independently measured room dimensions.
This setup has yet to be tested in the target room.

## Capture and scale

1. Use the POCO F7 main camera throughout the first run. Its 50 MP sensor has optical image
   stabilization. Start with one lens and a consistent shooting mode, avoid digital zoom, keep
   exposure steady where practical, and use a shutter speed that produces sharp frames.
   [POCO F7 specifications](https://www.po.co/global/product/poco-f7/specs/)
2. Fully extend two folding rulers and keep them straight and stationary. Lay them roughly at
   right angles on the floor so they span different directions. Pick identifiable graduations or
   marks with known separation and record that distance for each ruler. Capture both endpoints
   of each reference clearly from several viewpoints. Small temporary targets can make the
   endpoints easier to locate. [Scale-bar placement and processing](https://www.agisoft.com/pdf/tips_and_tricks/CHI_Calibrated_Scale_Bar_Placement_and_Processing.pdf)
3. Start with 50–150 sharp photos and roughly 60–80% overlap between consecutive views as
   trial settings. Walk around the room at multiple heights and aim across corners so photos
   connect adjacent walls, floor and ceiling. Keep lighting and the scene stable. Natural texture
   often supplies enough features. Add fixed, removable texture or targets across featureless
   walls where necessary. Texture at the bottom helps there, while the upper wall still needs
   coverage. Review photos for blur, reflections and missing viewpoints before leaving.
   [COLMAP capture guidance](https://colmap.github.io/tutorial.html) · [PhotoModeler accuracy guidance](https://www.photomodeler.com/pm-kb/getting-the-highest-possible-accuracy/)
4. Reconstruct the model, identify the endpoints and set the scale in metres. With a tool that
   applies a single scale factor, divide the known reference length by its measured model
   length and apply that factor uniformly. Use the second ruler as an initial check, or use both
   rulers as constraints if the software supports it and check other distances independently.
   Photogrammetry has one global scale. Perpendicular rulers sample different directions, and
   checks elsewhere in the room reveal distortions that a global scale factor cannot correct.
   [Metashape scale-bar controls and checks](https://www.agisoft.com/pdf/metashape-pro_1_7_en.pdf)

For this static-room trial, moving one camera supplies multiple viewpoints and avoids calibrating
or synchronizing two phones. With a fixed stereo rig, a short baseline makes distant features
harder to triangulate, and changes in camera settings can complicate calibration. Keep the second
F7 as a backup and leave the Redmi Note out of the baseline run. If a later run mixes cameras,
use separate camera calibration groups as supported by the reconstruction software. Moving
subjects need their own capture method. Two phones firing independently may capture different
subject positions. [COLMAP camera models](https://colmap.github.io/cameras.html)

## Verify the 30 mm target

Measure the same endpoints in the room and the scaled reconstruction. Before scaling, choose
independent distances that span the room and sample different heights and locations. Include
room width, room length, a diagonal, a vertical dimension such as floor-to-ceiling height, and
an opening width. Use a tape or laser distance meter for reference spans longer than the rulers.

Record each pair of endpoints, reference distance, model distance and absolute error in
millimetres. Report the maximum error and flag any required dimension that cannot be read
because an endpoint is missing or ambiguous. The pilot passes only if all selected checks are
measurable and every absolute error is at most 30 mm. Report ruler residuals separately when
the rulers were used for fitting. This result applies to the checked distances in that room.

## Processing options

- **Desktop mesh.** Try [Meshroom](https://github.com/alicevision/Meshroom) or
  [COLMAP](https://colmap.github.io/) for reconstruction. Check GPU support for the dense step
  in the installed build. Use [CloudCompare](https://www.cloudcompare.org/) or
  [Blender](https://www.blender.org/) to apply scale and inspect exported geometry. A readout in
  millimetres describes display precision.
- **Phone capture.** Try [KIRI Engine](https://www.kiriengine.app/features) or
  [Polycam](https://learn.poly.cam/hc/en-us) as app-based alternatives. Check the Android
  capture mode, processing location, export and scale controls for the selected plan before
  using it for the pilot. An exported model can be scaled on the desktop.
- **Floor plan only.** Google lists the POCO F7 as supporting the ARCore Depth API. CamToPlan
  advertises AR measurements and room plans. Magicplan's Android floor-plan guide describes
  manual drawing, templates and tracing, with scan modes listed as iOS-only. Apply the same
  independent distance checks to any resulting plan.
  [Google ARCore device list](https://developers.google.com/ar/devices) · [CamToPlan](https://play.google.com/store/apps/details?id=com.tasmanic.camtoplan) · [Magicplan Android guide](https://help.magicplan.app/creating-floor-plans-on-your-android-device)

Before running the pilot, record the target room and deliverable, available desktop OS and GPU,
or whether capture and processing must stay on the phone.
