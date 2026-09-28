<?php
$pager->setSurroundCount(2);
if (count($pager->links()) > 1) {
    ?>
	<nav class="my-5" aria-label="Page navigation">
		<ul class="pagination justify-content-center">
			<?php if ($pager->hasPrevious()) : ?>
				<li class="page-item">
					<a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="First">
						<i class="fal fa-arrow-alt-to-left"></i>
					</a>
				</li>
				<li class="page-item">
					<a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="Previous">
						<i class="fal fa-arrow-alt-left"></i>
					</a>
				</li>
			<?php endif ?>

			<?php foreach ($pager->links() as $link) : ?>
				<?php if ($link['active']) { ?>
					<li class="page-item active" aria-current="page">
						<span class="page-link">
							<?= $link['title'] ?>
							<span class="visually-hidden">(current)</span>
						</span>
					</li>
				<?php } else { ?>
					<li class="page-item">
						<a class="page-link" href="<?= $link['uri'] ?>">
							<?= $link['title'] ?>
						</a>
					</li>
				<?php } ?>
			<?php endforeach ?>

			<?php if ($pager->hasNext()) : ?>
				<li class="page-item">
					<a class="page-link" href="<?= $pager->getNext() ?>" aria-label="Next">
						<i class="fal fa-arrow-alt-right"></i>
					</a>
				</li>
				<li class="page-item">
					<a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Last">
						<i class="fal fa-arrow-alt-to-right"></i>
					</a>
				</li>
			<?php endif ?>
		</ul>
	</nav>
<?php
} ?>
